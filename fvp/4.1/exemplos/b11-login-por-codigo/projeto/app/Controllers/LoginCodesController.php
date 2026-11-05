<?php

namespace App\Controllers;

use App\Models\User;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Lib\FlashMessage;

/**
 * Login por código (FVP B11), em duas etapas:
 *   GET  /login/code         new           formulário de e-mail
 *   POST /login/code         create        gera o código (User), guarda o pedido (Auth), mostra o código
 *   GET  /login/code/verify  verify        formulário do código (só com pedido pendente)
 *   POST /login/code/verify  authenticate  User::authenticateWithLoginCode -> Auth::login
 *
 * Controller fino: nenhuma regra aqui. Quem decide se o código vale é o model
 * (authenticateWithLoginCode, hasPendingLoginCode); quem cuida da sessão é o Auth.
 * Este controller só lê o Request, chama os dois e escolhe flash + redirect (PRG).
 * O return depois de cada redirectTo existe porque, nos testes de controller, o
 * redirectTo é trocado por um echo e não encerra a ação.
 * Próximo passo: app/views/login_codes/new.phtml e verify.phtml.
 */
class LoginCodesController extends Controller
{
    public function new(): void
    {
        $title = 'Entrar com código';
        $this->render('login_codes/new', compact('title'));
    }

    public function create(Request $request): void
    {
        $email = $this->field($request, 'email');
        $user = $email === '' ? null : User::findByEmail($email);

        if ($user === null) {
            // Sem envio de e-mail no projeto, o código vai para a tela; por isso é
            // preciso saber quem é. Em produção a mensagem seria neutra.
            FlashMessage::danger('E-mail não cadastrado!');
            $this->redirectTo(route('login_codes.new'));
            return;
        }

        $code = $user->generateLoginCode();
        Auth::startLoginCode($user);

        // Único momento em que o código existe em texto puro fora do navegador.
        FlashMessage::success("Seu código de acesso é {$code}. Vale por 5 minutos.");
        $this->redirectTo(route('login_codes.verify'));
    }

    public function verify(): void
    {
        if (Auth::loginCodeUser() === null) {
            FlashMessage::danger('Informe o e-mail para receber um código.');
            $this->redirectTo(route('login_codes.new'));
            return;
        }

        $title = 'Informe o código';
        $this->render('login_codes/verify', compact('title'));
    }

    public function authenticate(Request $request): void
    {
        // Verificação 8: só quem pediu o código neste navegador chega aqui.
        $user = Auth::loginCodeUser();
        if ($user === null) {
            FlashMessage::danger('Informe o e-mail para receber um código.');
            $this->redirectTo(route('login_codes.new'));
            return;
        }

        // Verificações 1 a 7: todas no model. Sucesso = código consumido (uso único).
        if ($user->authenticateWithLoginCode($this->field($request, 'code'))) {
            Auth::clearLoginCode();
            Auth::login($user);
            FlashMessage::success('Login realizado com sucesso!');
            $this->redirectTo(route('dashboard.index'));
            return;
        }

        // Falhou e não sobrou código válido (expirou ou esgotou): peça outro.
        if (!$user->hasPendingLoginCode()) {
            Auth::clearLoginCode();
            FlashMessage::danger('Código expirado ou tentativas esgotadas. Peça outro.');
            $this->redirectTo(route('login_codes.new'));
            return;
        }

        // Falhou, mas ainda há tentativas: volta ao formulário do código.
        FlashMessage::danger('Código inválido!');
        $this->redirectTo(route('login_codes.verify'));
    }

    /**
     * Lê login_code[campo] como string, sem warning se o formulário vier vazio ou
     * forjado (nunca $_POST; nunca $params['x'] direto num array que pode ser null).
     */
    private function field(Request $request, string $name): string
    {
        $params = $request->getParam('login_code', []);
        $value = is_array($params) ? ($params[$name] ?? '') : '';

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
