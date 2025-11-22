<?php

namespace LBHurtado\HyperVerge\Tests\Unit;

use LBHurtado\HyperVerge\Data\HypervergeCredentials;
use LBHurtado\HyperVerge\Tests\TestCase;

class HypervergeCredentialsTest extends TestCase
{
    public function test_creates_credentials_from_config()
    {
        config([
            'hyperverge.app_id' => 'config_app_id',
            'hyperverge.app_key' => 'config_app_key',
            'hyperverge.url_workflow' => 'config_workflow',
            'hyperverge.base_url' => 'https://config.hyperverge.co',
        ]);

        $credentials = HypervergeCredentials::fromConfig();

        $this->assertEquals('config_app_id', $credentials->appId);
        $this->assertEquals('config_app_key', $credentials->appKey);
        $this->assertEquals('config_workflow', $credentials->workflowId);
        $this->assertEquals('https://config.hyperverge.co', $credentials->baseUrl);
        $this->assertEquals('config', $credentials->source);
    }

    public function test_validates_complete_credentials()
    {
        $valid = new HypervergeCredentials(
            appId: 'app_id',
            appKey: 'app_key',
            workflowId: 'workflow'
        );

        $this->assertTrue($valid->isValid());
    }

    public function test_invalidates_incomplete_credentials()
    {
        $invalid = new HypervergeCredentials(
            appId: '',
            appKey: 'app_key',
            workflowId: 'workflow'
        );

        $this->assertFalse($invalid->isValid());
    }

    public function test_converts_to_array()
    {
        $credentials = new HypervergeCredentials(
            appId: 'test_app',
            appKey: 'test_key',
            workflowId: 'test_workflow',
            baseUrl: 'https://test.co'
        );

        $array = $credentials->toArray();

        $this->assertArrayHasKey('app_id', $array);
        $this->assertArrayHasKey('app_key', $array);
        $this->assertArrayHasKey('workflow_id', $array);
        $this->assertArrayHasKey('base_url', $array);
        $this->assertEquals('test_app', $array['app_id']);
    }

    public function test_masks_sensitive_data_in_debug_array()
    {
        $credentials = new HypervergeCredentials(
            appId: 'very_long_app_id_string',
            appKey: 'very_long_app_key_string',
            workflowId: 'workflow'
        );

        $debug = $credentials->toDebugArray();

        $this->assertStringContainsString('****', $debug['app_id']);
        $this->assertStringContainsString('****', $debug['app_key']);
        $this->assertStringNotContainsString('very_long_app_id_string', $debug['app_id']);
        $this->assertEquals('workflow', $debug['workflow_id']); // Not masked
    }

    public function test_masks_short_values_completely()
    {
        $credentials = new HypervergeCredentials(
            appId: 'short',
            appKey: 'key',
            workflowId: 'w'
        );

        $debug = $credentials->toDebugArray();

        $this->assertEquals(str_repeat('*', 5), $debug['app_id']);
        $this->assertEquals(str_repeat('*', 3), $debug['app_key']);
    }

    public function test_preserves_first_and_last_four_chars_for_long_values()
    {
        $credentials = new HypervergeCredentials(
            appId: 'abcdefghijklmnop', // 16 chars
            appKey: 'test_app_key',
            workflowId: 'workflow'
        );

        $debug = $credentials->toDebugArray();

        $this->assertStringStartsWith('abcd', $debug['app_id']);
        $this->assertStringEndsWith('mnop', $debug['app_id']);
        $this->assertStringContainsString('****', $debug['app_id']);
    }
}
