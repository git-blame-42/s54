<?php

namespace ${NAMESPACE};

use App\Models\\${Model};
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

class ${NAME} extends Controller
{
    private const PER_PAGE = 5;

    public function index(Request ${DS}request): void
    {
        ${DS}paginator = ${Model}::paginate(
            page: max(1, (int) ${DS}request->getParam('page', 1)),
            per_page: self::PER_PAGE,
            route: '${rota}.paginate'
        );
        ${DS}${lista} = ${DS}paginator->registers();

        ${DS}title = '${rotulo_plural}';
        ${DS}this->render('${pasta_view}/index', compact('${lista}', 'paginator', 'title'));
    }

    public function show(Request ${DS}request): void
    {
        ${DS}${item} = ${Model}::findById((int) ${DS}request->getParam('id'));
        if (${DS}${item} === null) {
            ${DS}this->notFound();
            return;
        }

        ${DS}title = '${rotulo}';
        ${DS}this->render('${pasta_view}/show', compact('${item}', 'title'));
    }

    public function new(): void
    {
        ${DS}${item} = new ${Model}();

        ${DS}title = 'Novo ${rotulo}';
        ${DS}this->render('${pasta_view}/new', compact('${item}', 'title'));
    }

    public function create(Request ${DS}request): void
    {
        ${DS}${item} = new ${Model}(${DS}request->getParam('${item}', []));

        if (${DS}${item}->save()) {
            FlashMessage::success('${rotulo} cadastrado com sucesso!');
            ${DS}this->redirectTo(route('${rota}.show', ['id' => ${DS}${item}->id]));
        } else {
            FlashMessage::danger('Existem dados incorretos! Por favor, verifique!');
            ${DS}title = 'Novo ${rotulo}';
            ${DS}this->render('${pasta_view}/new', compact('${item}', 'title'));
        }
    }

    public function edit(Request ${DS}request): void
    {
        ${DS}${item} = ${Model}::findById((int) ${DS}request->getParam('id'));
        if (${DS}${item} === null) {
            ${DS}this->notFound();
            return;
        }

        ${DS}title = 'Editar ${rotulo}';
        ${DS}this->render('${pasta_view}/edit', compact('${item}', 'title'));
    }

    public function update(Request ${DS}request): void
    {
        ${DS}${item} = ${Model}::findById((int) ${DS}request->getParam('id'));
        if (${DS}${item} === null) {
            ${DS}this->notFound();
            return;
        }
        foreach (${DS}request->getParam('${item}', []) as ${DS}field => ${DS}value) {
            ${DS}${item}->${DS}field = ${DS}value;
        }

        if (${DS}${item}->save()) {
            FlashMessage::success('${rotulo} atualizado com sucesso!');
            ${DS}this->redirectTo(route('${rota}.show', ['id' => ${DS}${item}->id]));
        } else {
            FlashMessage::danger('Existem dados incorretos! Por favor, verifique!');
            ${DS}title = 'Editar ${rotulo}';
            ${DS}this->render('${pasta_view}/edit', compact('${item}', 'title'));
        }
    }

    public function destroy(Request ${DS}request): void
    {
        ${DS}${item} = ${Model}::findById((int) ${DS}request->getParam('id'));
        if (${DS}${item} === null) {
            ${DS}this->notFound();
            return;
        }

        if (${DS}${item}->destroy()) {
            FlashMessage::success('${rotulo} removido com sucesso!');
        } else {
            FlashMessage::danger('Não foi possível remover!');
        }

        ${DS}this->redirectTo(route('${rota}.index'));
    }

    private function notFound(): void
    {
        FlashMessage::danger('${rotulo} não encontrado!');
        ${DS}this->redirectTo(route('${rota}.index'));
    }
}
