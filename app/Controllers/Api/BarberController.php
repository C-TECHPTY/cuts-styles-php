<?php
// app/Controllers/Api/BarberController.php
namespace App\Controllers\Api;

use App\Models\Barber;

class BarberController extends BaseApiController
{
    private $barberModel;
    
    public function __construct()
    {
        $this->barberModel = new Barber();
    }
    
    // GET /api/v1/barbers/nearby?lat=8.9738&lng=-79.5208&radio=10
    public function getNearbyBarbers()
    {
        $lat = $_GET['lat'] ?? 0;
        $lng = $_GET['lng'] ?? 0;
        $radio = $_GET['radio'] ?? 10;
        
        if (!$lat || !$lng) {
            $this->errorResponse('Se requieren latitud y longitud', 400);
        }
        
        $barberos = $this->barberModel->getBarbersNearby($lat, $lng, $radio);
        $this->successResponse($barberos, 'Barberos cercanos obtenidos');
    }
    
    // GET /api/v1/barbers/{id}
    public function getBarber($id)
    {
        $barbero = $this->barberModel->getBarberById($id);
        
        if ($barbero) {
            $this->successResponse($barbero, 'Barbero encontrado');
        } else {
            $this->errorResponse('Barbero no encontrado', 404);
        }
    }
    
    // POST /api/v1/barbers/status
    public function updateStatus()
    {
        $input = $this->getJsonInput();
        $this->validateRequiredFields($input, ['barbero_id', 'is_available']);
        
        $result = $this->barberModel->updateAvailability($input['barbero_id'], $input['is_available']);
        
        if ($result) {
            $this->successResponse(null, 'Estado actualizado');
        } else {
            $this->errorResponse('Error al actualizar estado', 400);
        }
    }
}