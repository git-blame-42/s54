<?php

namespace App\Controllers\Admin;

use App\Models\User;
use Core\Http\Controllers\Controller;
use Core\Http\Request;

class UsersController extends Controller
{
    public function index(Request $request): void
    {
        $paginator = User::paginate(
            page: (int) $request->getParam('page', 1),
            per_page: 10,
            route: 'admin.users.paginate'
        );
        $users = $paginator->registers();

        // Mesma ação, duas representações: JSON quando o cliente pede
        // (header Accept: application/json), HTML no restante dos casos.
        if ($request->acceptJson()) {
            $this->renderJson('admin/users/index', compact('users', 'paginator'));
        } else {
            $title = 'Usuários';
            $this->render('admin/users/index', compact('users', 'paginator', 'title'));
        }
    }
}
