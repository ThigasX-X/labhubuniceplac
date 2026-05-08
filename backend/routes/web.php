<?php
/**
 * Tabela de rotas: page => [Controller, método]
 * Cada método pode verificar internamente se é GET ou POST.
 */
return [
    'login'           => ['AuthController',       'login'],
    'cadastro'        => ['AuthController',       'cadastro'],
    'verificar'       => ['AuthController',       'verificar'],
    'google'          => ['AuthController',       'google'],
    'logout'          => ['AuthController',       'logout'],

    'professor'       => ['ProfessorController',  'index'],

    'coordenador'     => ['CoordenadorController','index'],
    'kanban'          => ['CoordenadorController','kanban'],     // AJAX drag-drop

    'suporte'         => ['SuporteController',    'index'],

    'api/check-sos'        => ['ApiController',  'checkSos'],
    'api/check-sos-status' => ['ApiController',  'checkSosStatus'],
];
