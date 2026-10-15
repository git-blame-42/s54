<?php

namespace App\Controllers\Admin;

use App\Models\User;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

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

        $title = 'Usuários';
        $this->render('admin/users/index', compact('users', 'paginator', 'title'));
    }

    public function update(Request $request): void
    {
        $user = User::findById((int) $request->getParam('id'));
        $params = $request->getParam('user');
        $role = is_array($params) ? (string) ($params['role'] ?? '') : '';

        if ($user === null) {
            FlashMessage::danger('Não foi possível alterar o perfil.');
        } elseif ((int) $user->id === (int) $this->currentUser()?->id) {
            // Rebaixar a si mesmo tiraria o acesso à área administrativa na hora.
            FlashMessage::danger('Você não pode alterar o próprio perfil.');
        } elseif (!$user->changeRole($role)) {
            FlashMessage::danger('Não foi possível alterar o perfil.');
        } else {
            FlashMessage::success('Perfil alterado com sucesso!');
        }

        $this->redirectTo(route('admin.users.index'));
    }
}
