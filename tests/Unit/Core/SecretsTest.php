<?php

namespace MxAgentTools\Tests\Unit\Core;

use MxAgentTools\Core\Secrets;
use PHPUnit\Framework\TestCase;

class SecretsTest extends TestCase
{
    public function testSecretKeys(): void
    {
        foreach (['mail_smtp_pass', 'ms2_payment_secret', 'api_token', 'ym_mp_token', 'mxapi.cursor_secret', 'dadata_api_key',
            'yandex_apikey', 'PRIVATE_cert', 'password_salt', 'hash_algo', 'SMTP_PASS', 'site_key'] as $key) {
            $this->assertTrue(Secrets::isSecretKey($key), $key);
        }
        foreach (['site_name', 'ms2_order_format_num', 'emailsender', 'catalog_collection_order', 'phone'] as $key) {
            $this->assertFalse(Secrets::isSecretKey($key), $key);
        }
    }

    public function testRowMasksValueAndContextsKeepsEmpty(): void
    {
        $this->assertSame(['key' => 'smtp_pass', 'value' => '***', 'xtype' => 'text-password'],
            Secrets::row(['key' => 'smtp_pass', 'value' => 'hunter2', 'xtype' => 'text-password']));
        $this->assertSame(['key' => 'api_key', 'value' => '', 'contexts' => ['web' => '***', 'mgr' => '']],
            Secrets::row(['key' => 'api_key', 'value' => '', 'contexts' => ['web' => 'k1', 'mgr' => '']]));
        $row = ['key' => 'site_name', 'value' => 'Ayris'];
        $this->assertSame($row, Secrets::row($row));
    }

    public function testOutputMasksSnapshotsDiffAndParams(): void
    {
        $out = Secrets::output([
            'mode' => 'plan',
            'targets' => ['setting:smtp_pass', 'resource:5'],
            'before' => ['setting:smtp_pass' => ['key' => 'smtp_pass', 'value' => 'old', 'xtype' => 'textfield'],
                'clientconfig:yandex_token' => ['key' => 'yandex_token', 'value' => 'tk', 'contexts' => ['web' => 'tw']],
                'setting:site_name' => ['key' => 'site_name', 'value' => 'Ayris'], 'resource:5' => ['fields' => ['pagetitle' => 'A']]],
            'after' => ['setting:smtp_pass' => ['key' => 'smtp_pass', 'value' => 'new'], 'setting:x_token' => null],
            'diff' => ['setting:smtp_pass value: «old» → «new»', 'setting:smtp_pass: создаётся',
                'clientconfig:yandex_token contexts.web: «a» → «b»', 'setting:smtp_pass xtype: «a» → «b»',
                'setting:site_name value: «A» → «B»', 'resource:5 fields.pagetitle: «A» → «B»'],
            'divergence' => ['setting:smtp_pass value: у бота «a» → сейчас «b» → станет «c»'],
            'params' => ['key' => 'smtp_pass', 'value' => 'new'],
            'hash' => 'abc',
        ]);
        $this->assertSame('***', $out['before']['setting:smtp_pass']['value']);
        $this->assertSame(['web' => '***'], $out['before']['clientconfig:yandex_token']['contexts']);
        $this->assertSame('Ayris', $out['before']['setting:site_name']['value']);
        $this->assertSame(['fields' => ['pagetitle' => 'A']], $out['before']['resource:5']);
        $this->assertSame('***', $out['after']['setting:smtp_pass']['value']);
        $this->assertNull($out['after']['setting:x_token']);
        $this->assertSame(['setting:smtp_pass value: значение скрыто (секрет)', 'setting:smtp_pass: создаётся',
            'clientconfig:yandex_token contexts.web: значение скрыто (секрет)', 'setting:smtp_pass xtype: «a» → «b»',
            'setting:site_name value: «A» → «B»', 'resource:5 fields.pagetitle: «A» → «B»'], $out['diff']);
        $this->assertSame(['setting:smtp_pass value: значение скрыто (секрет)'], $out['divergence']);
        $this->assertSame(['key' => 'smtp_pass', 'value' => '***'], $out['params']);
        $this->assertSame(['setting:smtp_pass', 'resource:5'], $out['targets']);
        $this->assertSame('abc', $out['hash']);
        $this->assertStringNotContainsString('new', json_encode($out));
    }

    public function testJournalListEntriesMasked(): void
    {
        $out = Secrets::output(['journal' => [['id' => 1, 'targets' => ['setting:smtp_pass'],
            'before' => ['setting:smtp_pass' => ['key' => 'smtp_pass', 'value' => 'old']]]]]);
        $this->assertSame('***', $out['journal'][0]['before']['setting:smtp_pass']['value']);
    }
}
