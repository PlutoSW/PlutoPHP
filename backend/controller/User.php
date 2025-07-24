<?php

namespace Pluto\Controller;

use Pluto\Core\Route;
use Pluto\Core\Controller;
use Pluto\Core\Error;
use Pluto\Core\System;
use Pluto\Model\User as ModelUser;

class User extends Controller
{
    private $model = null;

    public function __construct()
    {
        parent::__construct();
        $this->model = ModelUser::class;
    }

    #[Route(method: "GET", endpoint: "/", response: "template")]
    public function index(): \Pluto\Core\Response
    {
        try {
            $data = $this->model::SearchMany();
            return $this->response->template(["users" => $data], "user/index");
        } catch (\Throwable $th) {
            new Error($th);
            return $this->response->template([], 'errors/404', 404);
        }
    }

    #[Route(method: "POST", endpoint: "/user/update", response: "template")]
    public function update()
    {
        try {
            $data = System::$data;

            $user = $this->model::Load($data->id);

            if (!$user) {
                throw new \Exception("User not found!", 404);
            }
            $user->name = $data->name;
            $user->email = $data->email;
            $user->password = $data->password;
            $user->Save();

            return $this->response->redirect("/");
        } catch (\Throwable $th) {
            new Error($th);
            return $this->response->template([], 'errors/404', 404);
        }
    }

    #[Route(method: "POST", endpoint: "/api/user/update", response: "json")]
    public function updateWithApi()
    {
        try {
            $data = System::$data;

            $user = $this->model::Load($data->id);

            if (!$user) {
                throw new \Exception("User not found!", 404);
            }
            $user->name = $data->name;
            $user->email = $data->email;
            $user->password = $data->password;
            $user->Save();

            return $this->response->success(true);
        } catch (\Throwable $th) {
            new Error($th);
            return $this->response->error("Error", 404);
        }
    }
}
