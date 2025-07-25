<?php

namespace Pluto\Controller;

use Pluto\Core\Route; 
use Pluto\Core\Controller; 
use Pluto\Core\Error;
use Pluto\Model\Igration as ModelIgration;

class Igration extends Controller
{
    private $model = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->model = ModelIgration::class;
    }
    
    #[Route(method:"GET", endpoint:"/igration", response:"template")]
    public function index(): \Pluto\Core\Response
    {
        try {
            $data = [];
            return $this->response->template($data, "igration/index");
        } catch (\Throwable $th) {
            new Error($th);
            return $this->response->template([],'errors/404', 404);
        }
    }
}
?>