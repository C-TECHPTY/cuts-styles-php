<?php
// app/Controllers/Api/ServiceController.php
namespace App\Controllers\Api;

use App\Models\Service;

class ServiceController extends BaseApiController
{
    private $serviceModel;
    
    public function __construct()
    {
        $this->serviceModel = new Service();
    }
    
    // POST /api/v1/services/request
    public function requestService()
    {
        $input = $this->getJsonInput();
        $this->validateRequiredFields($input, ['cliente_id', 'tipo']);
        
        $result = $this->serviceModel->solicitarServicio(
            $input['cliente_id'],
            $input['tipo'],
            $input['notas'] ?? '',
            $input['horarios'] ?? []
        );
        
        if ($result) {
            $this->successResponse(['servicio_id' => $result], 'Servicio solicitado exitosamente');
        } else {
            $this->errorResponse('Error al solicitar servicio', 400);
        }
    }
    
    // GET /api/v1/services/pending
    public function getPendingServices()
    {
        $servicios = $this->serviceModel->getServiciosPendientes();
        $this->successResponse($servicios, 'Servicios pendientes obtenidos');
    }
    
    // POST /api/v1/services/accept
    public function acceptService()
    {
        $input = $this->getJsonInput();
        $this->validateRequiredFields($input, ['servicio_id', 'barbero_id', 'tiempo_estimado']);
        
        $result = $this->serviceModel->aceptarServicio(
            $input['servicio_id'],
            $input['barbero_id'],
            $input['tiempo_estimado'],
            $input['notas'] ?? ''
        );
        
        if ($result) {
            $this->successResponse(null, 'Servicio aceptado exitosamente');
        } else {
            $this->errorResponse('Error al aceptar servicio', 400);
        }
    }
    
    // POST /api/v1/services/complete
    public function completeService()
    {
        $input = $this->getJsonInput();
        $this->validateRequiredFields($input, ['servicio_id', 'duracion_real']);
        
        $result = $this->serviceModel->completarServicio(
            $input['servicio_id'],
            $input['duracion_real'],
            $input['notas'] ?? ''
        );
        
        if ($result) {
            $this->successResponse(null, 'Servicio completado exitosamente');
        } else {
            $this->errorResponse('Error al completar servicio', 400);
        }
    }
    
    // GET /api/v1/services/history/{cliente_id}
    public function getHistory($cliente_id)
    {
        $servicios = $this->serviceModel->getServiciosByCliente($cliente_id);
        $this->successResponse($servicios, 'Historial de servicios obtenido');
    }
}