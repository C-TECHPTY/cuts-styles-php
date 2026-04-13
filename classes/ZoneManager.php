<?php
// classes/ZoneManager.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/SystemSettings.php';

class ZoneManager {
    private PDO $conn;
    private SystemSettings $settings;
    private array $tableCache = [];

    public function __construct(?PDO $conn = null) {
        if ($conn instanceof PDO) {
            $this->conn = $conn;
        } else {
            $database = new Database();
            $this->conn = $database->getConnection();
        }

        $this->settings = new SystemSettings($this->conn);
    }

    public function hasTable(string $table): bool {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE " . $this->conn->quote($table));
            $this->tableCache[$table] = $stmt && $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            $this->tableCache[$table] = false;
        }

        return $this->tableCache[$table];
    }

    public function isEnabled(): bool {
        return $this->settings->getBool('zone_matching_enabled', false)
            && $this->hasTable('barber_zone_preferences')
            && $this->hasTable('service_zone_assignments');
    }

    public function requireServiceZone(): bool {
        return $this->settings->getBool('zone_require_service_zone', false);
    }

    public function getMode(): string {
        $mode = $this->settings->get('zone_matching_mode', 'preferred');
        return in_array($mode, ['preferred', 'strict'], true) ? $mode : 'preferred';
    }

    public function normalizeZone(string $value): string {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value ?? '');
        return mb_substr((string) $value, 0, 120);
    }

    public function normalizeSector(string $value): string {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value ?? '');
        return mb_substr((string) $value, 0, 120);
    }

    public function saveBarberCoverage(int $barberoId, string $zone, string $sectorsCsv = ''): bool {
        if ($barberoId <= 0 || !$this->hasTable('barber_zone_preferences')) {
            return false;
        }

        $zone = $this->normalizeZone($zone);
        $sectors = $this->normalizeSectorsCsv($sectorsCsv);

        try {
            $stmt = $this->conn->prepare("INSERT INTO barber_zone_preferences
                (barbero_id, zone_name, sectors_csv, is_active, updated_at, created_at)
                VALUES
                (:barbero_id, :zone_name, :sectors_csv, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    zone_name = VALUES(zone_name),
                    sectors_csv = VALUES(sectors_csv),
                    is_active = 1,
                    updated_at = NOW()");
            $stmt->bindValue(':barbero_id', $barberoId, PDO::PARAM_INT);
            $stmt->bindValue(':zone_name', $zone);
            $stmt->bindValue(':sectors_csv', $sectors);
            return $stmt->execute();
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudo guardar cobertura por zona: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return false;
        }
    }

    public function getBarberCoverage(int $barberoId): array {
        $defaults = [
            'zone_name' => '',
            'sectors_csv' => '',
            'sectors' => [],
            'has_zone' => false,
        ];

        if ($barberoId <= 0 || !$this->hasTable('barber_zone_preferences')) {
            return $defaults;
        }

        try {
            $stmt = $this->conn->prepare("SELECT zone_name, sectors_csv
                FROM barber_zone_preferences
                WHERE barbero_id = :barbero_id
                LIMIT 1");
            $stmt->bindValue(':barbero_id', $barberoId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $zone = $this->normalizeZone((string) ($row['zone_name'] ?? ''));
            $sectorsCsv = $this->normalizeSectorsCsv((string) ($row['sectors_csv'] ?? ''));

            return [
                'zone_name' => $zone,
                'sectors_csv' => $sectorsCsv,
                'sectors' => $this->explodeSectors($sectorsCsv),
                'has_zone' => $zone !== '',
            ];
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudo leer cobertura por zona: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return $defaults;
        }
    }

    public function assignServiceZone(int $serviceId, string $zone, string $sector = ''): bool {
        if ($serviceId <= 0 || !$this->hasTable('service_zone_assignments')) {
            return false;
        }

        $zone = $this->normalizeZone($zone);
        $sector = $this->normalizeSector($sector);

        try {
            $stmt = $this->conn->prepare("INSERT INTO service_zone_assignments
                (servicio_id, zone_name, sector_name, created_at, updated_at)
                VALUES
                (:servicio_id, :zone_name, :sector_name, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    zone_name = VALUES(zone_name),
                    sector_name = VALUES(sector_name),
                    updated_at = NOW()");
            $stmt->bindValue(':servicio_id', $serviceId, PDO::PARAM_INT);
            $stmt->bindValue(':zone_name', $zone);
            $stmt->bindValue(':sector_name', $sector);
            return $stmt->execute();
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudo asignar zona al servicio: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return false;
        }
    }

    public function registerAlertsForService(int $serviceId): void {
        if ($serviceId <= 0 || !$this->isEnabled() || !$this->hasTable('zone_alert_logs')) {
            return;
        }

        $serviceZone = $this->getServiceZone($serviceId);
        if (($serviceZone['zone_name'] ?? '') === '') {
            return;
        }

        try {
            $stmt = $this->conn->query("SELECT id FROM barberos WHERE is_available = 1 AND verificacion_status = 'verificado'");
            $barbers = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $insert = $this->conn->prepare("INSERT INTO zone_alert_logs (barbero_id, servicio_id, alert_type, created_at)
                VALUES (:barbero_id, :servicio_id, 'pending_request_in_zone', NOW())");

            foreach ($barbers as $barber) {
                $barberoId = (int) ($barber['id'] ?? 0);
                if ($barberoId <= 0) {
                    continue;
                }
                $coverage = $this->getBarberCoverage($barberoId);
                $match = $this->evaluateMatch($coverage, $serviceZone['zone_name'], $serviceZone['sector_name']);
                if ($match['level'] === 'none') {
                    continue;
                }
                $insert->bindValue(':barbero_id', $barberoId, PDO::PARAM_INT);
                $insert->bindValue(':servicio_id', $serviceId, PDO::PARAM_INT);
                $insert->execute();
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudieron registrar alertas por zona: ' . $e->getMessage(), __FILE__, __LINE__);
            }
        }
    }

    public function getServiceZone(int $serviceId): array {
        if ($serviceId <= 0 || !$this->hasTable('service_zone_assignments')) {
            return ['zone_name' => '', 'sector_name' => ''];
        }

        try {
            $stmt = $this->conn->prepare("SELECT zone_name, sector_name
                FROM service_zone_assignments
                WHERE servicio_id = :servicio_id
                LIMIT 1");
            $stmt->bindValue(':servicio_id', $serviceId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'zone_name' => $this->normalizeZone((string) ($row['zone_name'] ?? '')),
                'sector_name' => $this->normalizeSector((string) ($row['sector_name'] ?? '')),
            ];
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudo leer zona del servicio: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return ['zone_name' => '', 'sector_name' => ''];
        }
    }

    public function validateServiceZoneInput(string $zone, string $sector = ''): array {
        $zone = $this->normalizeZone($zone);
        $sector = $this->normalizeSector($sector);

        if (!$this->isEnabled()) {
            return ['valid' => true, 'zone' => $zone, 'sector' => $sector, 'message' => ''];
        }

        if ($this->requireServiceZone() && $zone === '') {
            return ['valid' => false, 'zone' => '', 'sector' => $sector, 'message' => 'Debes seleccionar o escribir una zona para la solicitud.'];
        }

        return ['valid' => true, 'zone' => $zone, 'sector' => $sector, 'message' => ''];
    }

    public function getAvailableBarbers(?string $requestedZone = null, ?string $requestedSector = null): array {
        $requestedZone = $this->normalizeZone((string) $requestedZone);
        $requestedSector = $this->normalizeSector((string) $requestedSector);

        $sql = "SELECT b.*, u.nombre, u.telefono";
        if ($this->isEnabled()) {
            $sql .= ", COALESCE(bzp.zone_name, '') AS zone_name, COALESCE(bzp.sectors_csv, '') AS sectors_csv";
        }
        $sql .= " FROM barberos b
                  JOIN users u ON b.user_id = u.id";
        if ($this->isEnabled()) {
            $sql .= " LEFT JOIN barber_zone_preferences bzp ON b.id = bzp.barbero_id";
        }
        $sql .= " WHERE b.is_available = 1 AND b.verificacion_status = 'verificado'";
        $sql .= " ORDER BY b.calificacion_promedio DESC LIMIT 30";

        try {
            $stmt = $this->conn->query($sql);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!$this->isEnabled()) {
                return $rows;
            }

            $mode = $this->getMode();
            $prioritized = [];
            $fallback = [];
            foreach ($rows as $row) {
                $match = $this->evaluateMatchForBarberRow($row, $requestedZone, $requestedSector);
                $row['zone_match_level'] = $match['level'];
                $row['zone_match_label'] = $match['label'];
                $row['zone_name'] = $match['zone_name'];
                $row['sector_name'] = $requestedSector;
                if ($mode === 'strict') {
                    if ($match['level'] !== 'none') {
                        $prioritized[] = $row;
                    }
                } else {
                    if ($match['level'] !== 'none') {
                        $prioritized[] = $row;
                    } else {
                        $fallback[] = $row;
                    }
                }
            }

            return $mode === 'strict' ? $prioritized : array_merge($prioritized, $fallback);
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudieron obtener barberos por zona: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return [];
        }
    }

    public function getPendingServicesForBarber(int $barberoId): array {
        $sql = "SELECT s.*, c.id as cliente_id, u.nombre as cliente_nombre, u.telefono, u.direccion";
        if ($this->isEnabled()) {
            $sql .= ", COALESCE(sza.zone_name, '') AS zone_name, COALESCE(sza.sector_name, '') AS sector_name";
        }
        $sql .= " FROM servicios s
                  JOIN clientes c ON s.cliente_id = c.id
                  JOIN users u ON c.user_id = u.id";
        if ($this->isEnabled()) {
            $sql .= " LEFT JOIN service_zone_assignments sza ON s.id = sza.servicio_id";
        }
        $sql .= " WHERE s.estado = 'pendiente'
                  ORDER BY s.fecha_solicitud ASC";

        try {
            $stmt = $this->conn->query($sql);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!$this->isEnabled() || $barberoId <= 0) {
                return $rows;
            }

            $coverage = $this->getBarberCoverage($barberoId);
            $mode = $this->getMode();
            $prioritized = [];
            $fallback = [];
            foreach ($rows as $row) {
                $match = $this->evaluateMatch($coverage, (string) ($row['zone_name'] ?? ''), (string) ($row['sector_name'] ?? ''));
                $row['zone_match_level'] = $match['level'];
                $row['zone_match_label'] = $match['label'];
                if ($mode === 'strict') {
                    if ($match['level'] !== 'none') {
                        $prioritized[] = $row;
                    }
                } else {
                    if ($match['level'] !== 'none') {
                        $prioritized[] = $row;
                    } else {
                        $fallback[] = $row;
                    }
                }
            }

            return $mode === 'strict' ? $prioritized : array_merge($prioritized, $fallback);
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudieron obtener servicios pendientes por zona: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return [];
        }
    }

    public function getZoneAlertSummary(int $barberoId): array {
        $summary = [
            'enabled' => $this->isEnabled(),
            'has_zone' => false,
            'zone_name' => '',
            'sectors_csv' => '',
            'matching_pending' => 0,
            'outside_pending' => 0,
            'alert_logs_today' => 0,
            'message' => '',
        ];

        if ($barberoId <= 0 || !$this->isEnabled()) {
            return $summary;
        }

        $coverage = $this->getBarberCoverage($barberoId);
        $summary['has_zone'] = $coverage['has_zone'];
        $summary['zone_name'] = $coverage['zone_name'];
        $summary['sectors_csv'] = $coverage['sectors_csv'];

        if (!$coverage['has_zone']) {
            $summary['message'] = 'Configura tu zona para recibir mejores coincidencias y alertas de solicitudes cercanas.';
            return $summary;
        }

        $allPending = $this->getPendingServicesForBarber($barberoId);
        foreach ($allPending as $row) {
            if (($row['zone_match_level'] ?? 'none') !== 'none') {
                $summary['matching_pending']++;
            } else {
                $summary['outside_pending']++;
            }
        }

        if ($this->hasTable('zone_alert_logs')) {
            $alertStmt = $this->conn->prepare("SELECT COUNT(*) FROM zone_alert_logs
                WHERE barbero_id = :barbero_id
                  AND DATE(created_at) = CURDATE()");
            $alertStmt->bindValue(':barbero_id', $barberoId, PDO::PARAM_INT);
            $alertStmt->execute();
            $summary['alert_logs_today'] = (int) ($alertStmt->fetchColumn() ?: 0);
        }

        $summary['message'] = $summary['matching_pending'] > 0
            ? 'Tienes solicitudes compatibles con tu zona.'
            : 'No hay solicitudes nuevas compatibles con tu zona por ahora.';

        return $summary;
    }

    public function getKnownZones(): array {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            $zones = [];
            $stmt1 = $this->conn->query("SELECT DISTINCT zone_name FROM barber_zone_preferences WHERE zone_name IS NOT NULL AND zone_name <> ''");
            foreach (($stmt1 ? $stmt1->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
                $zones[] = $this->normalizeZone((string) $row['zone_name']);
            }
            $stmt2 = $this->conn->query("SELECT DISTINCT zone_name FROM service_zone_assignments WHERE zone_name IS NOT NULL AND zone_name <> ''");
            foreach (($stmt2 ? $stmt2->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
                $zones[] = $this->normalizeZone((string) $row['zone_name']);
            }
            $zones = array_values(array_unique(array_filter($zones)));
            sort($zones);
            return $zones;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function evaluateMatchForBarberRow(array $row, string $requestedZone, string $requestedSector): array {
        $coverage = [
            'zone_name' => $this->normalizeZone((string) ($row['zone_name'] ?? '')),
            'sectors_csv' => $this->normalizeSectorsCsv((string) ($row['sectors_csv'] ?? '')),
            'sectors' => $this->explodeSectors((string) ($row['sectors_csv'] ?? '')),
            'has_zone' => $this->normalizeZone((string) ($row['zone_name'] ?? '')) !== '',
        ];

        $match = $this->evaluateMatch($coverage, $requestedZone, $requestedSector);
        $match['zone_name'] = $coverage['zone_name'];
        return $match;
    }

    private function evaluateMatch(array $coverage, string $requestedZone, string $requestedSector): array {
        $barberZone = $this->normalizeZone((string) ($coverage['zone_name'] ?? ''));
        $requestedZone = $this->normalizeZone($requestedZone);
        $requestedSector = $this->normalizeSector($requestedSector);
        $barberSectors = $coverage['sectors'] ?? [];

        if ($requestedZone === '') {
            return ['level' => 'none', 'label' => 'Sin zona'];
        }

        if ($barberZone !== '' && mb_strtolower($barberZone) === mb_strtolower($requestedZone)) {
            if ($requestedSector !== '' && $barberSectors !== []) {
                foreach ($barberSectors as $sector) {
                    if (mb_strtolower($sector) === mb_strtolower($requestedSector)) {
                        return ['level' => 'sector', 'label' => 'Misma zona y sector'];
                    }
                }
            }
            return ['level' => 'zone', 'label' => 'Misma zona'];
        }

        return ['level' => 'none', 'label' => 'Fuera de zona'];
    }

    private function normalizeSectorsCsv(string $value): string {
        $items = $this->explodeSectors($value);
        return implode(', ', $items);
    }

    private function explodeSectors(string $value): array {
        $parts = preg_split('/[,;\n]+/', $value) ?: [];
        $items = [];
        foreach ($parts as $part) {
            $sector = $this->normalizeSector($part);
            if ($sector !== '') {
                $items[] = $sector;
            }
        }
        return array_values(array_unique($items));
    }
}
