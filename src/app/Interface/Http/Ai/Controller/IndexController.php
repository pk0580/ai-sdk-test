<?php

declare(strict_types=1);

namespace App\Interface\Http\Ai\Controller;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

final class IndexController
{
    public function __invoke(): Factory|View
    {
        return view('chat');
    }
}
