<?php

require __DIR__ . '/../../config/bootstrap.php';

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventKind;
use App\Models\User;
use Core\Database\Database;

Database::migrate();

$admin = new User([
    'name' => 'Administrador',
    'email' => 'admin@example.com',
    'password' => '123456',
    'password_confirmation' => '123456',
    'role' => 'admin'
]);
$admin->save();

$user = new User([
    'name' => 'Usuário',
    'email' => 'user@example.com',
    'password' => '123456',
    'password_confirmation' => '123456'
]);
$user->save();

echo "Usuários criados: {$admin->email} (admin) e {$user->email} (participant), senha 123456\n";

// Participantes extras, para haver Inscrições de mais de uma pessoa.
$participants = [$user];
foreach (['Mariana Souza' => 'mariana', 'Carlos Pereira' => 'carlos', 'Fernanda Lima' => 'fernanda'] as $name => $nick) {
    $participant = new User([
        'name' => $name,
        'email' => "{$nick}@example.com",
        'password' => '123456',
        'password_confirmation' => '123456'
    ]);
    $participant->save();
    $participants[] = $participant;
}

echo count($participants) . " Participantes criados (user@, mariana@, carlos@, fernanda@), senha 123456\n";

// Eventos: colunas title, description, kind, modality, audience, dias a partir de hoje, location, capacity.
// As datas são relativas (hoje + N dias, N >= 14) para nunca ficarem no passado, já que a validação
// exige starts_on não passado; assim todos os Eventos (inclusive o lotado) continuam editáveis.
$events = [
    [
        'Semana Acadêmica de Informática 2026',
        'Semana com palestras, minicursos e maratona de programação promovida pelo curso de Sistemas para Internet.',
        EventKind::AcademicWeek->value, 'in_person', 'students,professors,community', 14, 'Bloco D, Auditório Central', 3,
    ],
    [
        'Introdução a APIs REST com PHP',
        'Palestra sobre os princípios do REST, verbos HTTP e boas práticas de projeto de APIs em PHP.',
        EventKind::Lecture->value, 'online', 'students,professors', 15, 'Microsoft Teams (link enviado aos inscritos)', 80,
    ],
    [
        'Oficina de Git e GitHub para Iniciantes',
        'Oficina prática de versionamento: commits, branches, pull requests e resolução de conflitos.',
        EventKind::Workshop->value, 'in_person', 'students', 21, 'Bloco B, Laboratório de Informática B203', 25,
    ],
    [
        'Minicurso de Docker para Desenvolvimento Web',
        'Minicurso de quatro horas sobre contêineres, imagens e docker compose aplicados a projetos web.',
        EventKind::ShortCourse->value, 'hybrid', 'students,professors,staff', 22, 'Bloco B, Laboratório B205 e transmissão online', 30,
    ],
    [
        'Palestra: Carreira em Tecnologia na Região Centro-Sul',
        'Profissionais de empresas da região contam como foi a entrada no mercado e quais competências são mais procuradas.',
        EventKind::Lecture->value, 'in_person', 'students,community', 27, 'Bloco A, Auditório do Bloco A', 120,
    ],
    [
        'Oficina de Modelagem de Banco de Dados',
        'Do diagrama entidade-relacionamento ao esquema MySQL, com exercícios de normalização e chaves estrangeiras.',
        EventKind::Workshop->value, 'in_person', 'students,professors', 29, 'Bloco B, Laboratório de Informática B201', 20,
    ],
    [
        'Semana de Ciência e Tecnologia do Campus',
        'Mostra de projetos de ensino, pesquisa e extensão do campus Guarapuava, aberta à comunidade externa.',
        EventKind::AcademicWeek->value, 'in_person', 'students,professors,staff,community', 34, 'Bloco C, Pátio Central', 300,
    ],
    [
        'Minicurso de Testes Automatizados com PHPUnit e Codeception',
        'Testes de unidade, integração e aceitação em uma aplicação PHP, com execução no GitHub Actions.',
        EventKind::ShortCourse->value, 'online', 'students,professors', 36, 'Google Meet (link enviado aos inscritos)', 40,
    ],
    [
        'Palestra: Inteligência Artificial no Ensino de Programação',
        'Debate sobre usos responsáveis de assistentes de IA em sala de aula e na avaliação de trabalhos.',
        EventKind::Lecture->value, 'hybrid', 'professors,staff', 41, 'Bloco A, Sala de Reuniões A110 e transmissão online', 50,
    ],
    [
        'Oficina de Acessibilidade em Interfaces Web',
        'Boas práticas de HTML semântico, contraste, navegação por teclado e leitores de tela.',
        EventKind::Workshop->value, 'in_person', 'students,professors,staff', 43, 'Bloco B, Laboratório de Informática B204', 24,
    ],
    [
        'Minicurso de Estruturas de Dados em PHP',
        'Pilhas, filas, listas e árvores implementadas em PHP, com análise de complexidade.',
        EventKind::ShortCourse->value, 'in_person', 'students', 48, 'Bloco A, Sala A205', 35,
    ],
    [
        'Palestra: Segurança no Desenvolvimento de Aplicações Web',
        'Injeção de SQL, XSS, CSRF e armazenamento seguro de senhas, com exemplos de ataques reais.',
        EventKind::Lecture->value, 'online', 'students,professors,community', 50, 'Microsoft Teams (link enviado aos inscritos)', 100,
    ],
    [
        'Oficina de Prototipação no Figma',
        'Wireframes, componentes e protótipos navegáveis para validar telas antes de programar.',
        EventKind::Workshop->value, 'hybrid', 'students,community', 55, 'Bloco C, Laboratório C102 e transmissão online', 30,
    ],
    [
        'Semana de Boas-Vindas aos Calouros 2027',
        'Recepção dos ingressantes com apresentação dos cursos, da biblioteca, dos laboratórios e dos projetos de extensão.',
        EventKind::AcademicWeek->value, 'in_person', 'students,staff', 69, 'Bloco D, Auditório Central', 150,
    ],
    [
        'Minicurso de Introdução a SQL e MySQL',
        'Consultas, junções, agregações e subconsultas sobre uma base de dados de eventos acadêmicos.',
        EventKind::ShortCourse->value, 'in_person', 'students,community', 75, 'Bloco B, Laboratório de Informática B203', 28,
    ],
    [
        'Palestra: Ciência de Dados Aplicada à Agricultura',
        'Uso de dados e modelos preditivos em propriedades rurais da região, com casos de cooperativas locais.',
        EventKind::Lecture->value, 'hybrid', 'students,professors,staff,community', 82, 'Bloco A, Auditório do Bloco A e transmissão online', 90,
    ],
];

$eventModels = [];
foreach ($events as [$title, $description, $kind, $modality, $audience, $daysAhead, $location, $capacity]) {
    $event = new Event([
        'user_id' => $admin->id,
        'title' => $title,
        'description' => $description,
        'kind' => $kind,
        'modality' => $modality,
        'starts_on' => date('Y-m-d', strtotime("+{$daysAhead} days")),
        'location' => $location,
        'capacity' => $capacity,
    ]);
    $event->setAudienceList(explode(',', $audience));
    if (!$event->save()) {
        throw new RuntimeException("Evento inválido no populate: {$title}");
    }
    $eventModels[] = $event;
}

echo count($eventModels) . " Eventos criados pelo Administrador\n";

// Inscrições (event_user): índice do Evento => índices dos Participantes.
// O Evento 0 tem capacity 3 e três Inscrições (lotado): serve de caso para P02 (capacity >= Inscrições).
// Os Eventos com Inscrições não podem ser removidos (P03).
$enrollments = [
    0 => [0, 1, 2],
    1 => [0, 1],
    2 => [1],
    3 => [0, 2, 3],
    5 => [3],
    8 => [0, 1, 2, 3],
];

$total = 0;
foreach ($enrollments as $eventIndex => $participantIndexes) {
    foreach ($participantIndexes as $participantIndex) {
        $enrollment = new Enrollment([
            'event_id' => $eventModels[$eventIndex]->id,
            'user_id' => $participants[$participantIndex]->id,
        ]);
        if (!$enrollment->save()) {
            throw new RuntimeException('Inscrição inválida no populate');
        }
        $total++;
    }
}

echo "$total Inscrições criadas em " . count($enrollments) . " Eventos\n";
