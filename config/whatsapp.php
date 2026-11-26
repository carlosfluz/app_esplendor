<?php

return [
    // Versão da Graph API. Confira a mais recente em developers.facebook.com/docs/graph-api/changelog
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v24.0'),

    'token' => env('WHATSAPP_TOKEN'),                    // token de usuário do sistema (permanente)
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'waba_id' => env('WHATSAPP_WABA_ID'),                // necessário para criar Flows
    'app_secret' => env('WHATSAPP_APP_SECRET'),          // valida X-Hub-Signature-256
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),      // o mesmo cadastrado no painel do webhook

    // true enquanto o Flow estiver em rascunho: envia com mode=draft (só funciona para números de teste)
    'flows_modo_rascunho' => env('WHATSAPP_FLOWS_RASCUNHO', true),

    // Checklist aberto e não enviado expira depois de X minutos
    'checklist_expira_minutos' => env('CHECKLIST_EXPIRA_MINUTOS', 120),

    // Onde as fotos baixadas da Meta são guardadas
    'disco_fotos' => env('CHECKLIST_DISCO_FOTOS', 'local'),
];
