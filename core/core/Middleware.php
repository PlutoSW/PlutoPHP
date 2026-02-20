<?php

namespace Pluto;

use Closure;
use Pluto\Request;

interface Middleware
{
    public function handle(Request $request, Closure $next);
}