<?php

namespace Pluto\Controller;

use Pluto\Core\Route; 
use Pluto\Core\Controller; 
use Pluto\Core\Error;
use Pluto\Model\Test as ModelTest;

class Test extends Controller
{
    private $model = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->model = ModelTest::class;
    }
    
    #[Route(method:"GET", endpoint:"/test", response:"template")]
    public function index(): \Pluto\Core\Response
    {
        try {
            $data = [];
            return $this->response->template($data, "test/index");
        } catch (\Throwable $th) {
            new Error($th);
            return $this->response->template([],'errors/404', 404);
        }
    }
}
?>