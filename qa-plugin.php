<?php
if (!defined('QA_VERSION')) {
    header('Location: ../../');
    exit;
}

qa_register_plugin_module(
    'page',
    'create-poll.php',
    'create_poll',
    'For creating a poll'
);

qa_register_plugin_module(
    'page',
    'update-poll.php',
    'update_poll',
    'For updating a poll'
);

qa_register_plugin_module(
    'page',
    'user-nomination.php',
    'user_nomination',
    'For nominating themselves'
);

qa_register_plugin_module(
    'page',
    'list-polls.php',
    'list_polls',
    'For listing all the avaialable polls'
);

qa_register_plugin_module(
    'page',
    'casting.php',
    'casting',
    'For casting the vote in the polling'
);

qa_register_plugin_module(
    'page',
    'poll-stats.php',
    'poll_stats',
    'For Stats purpose'
);