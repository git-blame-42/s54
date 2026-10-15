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

    public function destroy(Request $request): void
    {
        $user = User::findById((int) $request->getParam('id'));
        $currentId = (int) $this->currentUser()?->id;

        // Não existe ou é o próprio administrador logado: nada a remover.
        if ($user === null || (int) $user->id === $currentId) {
            FlashMessage::danger('Não foi possível remover o usuário.');
        } else {
            $user->destroy();
            FlashMessage::success('Usuário removido com sucesso!');
        }

        $this->redirectTo(route('admin.users.index'));
    }
}
