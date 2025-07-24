<?php

namespace Pluto\Core;

use Pluto\Core\Response;

abstract class Controller
{
    protected Response $response;

    public function __construct()
    {
        $this->response = new Response($this);
    }


    public static function hasPermission($controller, $method): bool
    {
        $return = false;
        if (isset(System::$permissions->{"*"}) && System::$permissions->{"*"}) {
            return true;
        }

        $expController = \explode("\\", $controller);
        $controller = \end($expController);
        foreach (System::$currentUser?->permissions as $permission) {
            if ($permission->controller == $controller && ($permission->method == $method || $permission->method == "*") && $permission->access == 1) {
                $return = true;
                break;
            }
        }
        return $return;
    }

    abstract public function index(): Response;
}
