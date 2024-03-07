<?php

use Phan\Issue;

return [
    'target_php_version' => '7.3',
    'minimum_target_php_version' => '7.3',

    'directory_list' => [
        'src',
        'vendor',
    ],

    'exclude_analysis_directory_list' => [
        'vendor/'
    ],

    'minimum_severity' => Issue::SEVERITY_LOW,

    'backward_compatibility_checks' => false,

    /**
     * @todo remove
     * @see https://github.com/phan/phan/issues/2709
     */
    'strict_param_checking' => false,
    'null_casts_as_any_type' => true,

    'suppress_issue_types' => [
        // minimum_target_php_version not enough to suppress this
        'PhanCompatibleTrailingCommaParameterList',
    ]
];
