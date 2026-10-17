<?php
/** Analytics uninstall cleanup. */
if (!defined('WP_UNINSTALL_PLUGIN') || !class_exists('\\MillionDollarScript\\Extensions\\CleanupPolicy')) { exit; }
\MillionDollarScript\Extensions\CleanupPolicy::cleanup('mds-google-analytics', [
    'option_prefixes' => ['mds_ga_'],
    'transient_prefixes' => ['mds_ga_'],
]);
