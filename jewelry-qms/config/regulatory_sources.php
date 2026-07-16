<?php
declare(strict_types=1);

return [
    'sources' => [
        'samr_rkjcs_notice' => [
            'name' => '国家市场监督管理总局认可检测司通知公告',
            'mode' => 'html_list',
            'entry_url' => 'https://www.samr.gov.cn/rkjcs/tzgg/index.html',
            'allowed_hosts' => ['www.samr.gov.cn'],
            'item_xpath' => '//ul[contains(concat(" ", normalize-space(@class), " "), " news-list ")]/li',
        ],
        'cnas_lab_notice' => [
            'name' => 'CNAS 实验室专门通知',
            'mode' => 'html_list',
            'entry_url' => 'https://www.cnas.org.cn/rkfw/sys/zxtz/index.html',
            'allowed_hosts' => ['www.cnas.org.cn'],
            'item_xpath' => '//ul[contains(concat(" ", normalize-space(@class), " "), " notice-list ")]/li',
        ],
        'cnas_lab_rules' => [
            'name' => 'CNAS 实验室认可要求',
            'mode' => 'html_list',
            'entry_url' => 'https://www.cnas.org.cn/rkfw/sys/rkyq/rkzz/index.html',
            'allowed_hosts' => ['www.cnas.org.cn'],
            'item_xpath' => '//ul[contains(concat(" ", normalize-space(@class), " "), " notice-list ")]/li',
        ],
        'xinjiang_samr_notice' => [
            'name' => '新疆维吾尔自治区市场监督管理局通知公告',
            'mode' => 'html_list',
            'entry_url' => 'https://scjgj.xinjiang.gov.cn/xjaic/tzgg/',
            'allowed_hosts' => ['scjgj.xinjiang.gov.cn'],
            'item_xpath' => '//ul[contains(concat(" ", normalize-space(@class), " "), " news-list ")]/li',
        ],
        'cma_capability_query' => [
            'name' => '检验检测机构资质认定证书查询',
            'mode' => 'manual_only',
            'entry_url' => 'https://cma.caqit.org.cn/',
            'allowed_hosts' => ['cma.caqit.org.cn'],
        ],
    ],
];
