<?php

use Lettermint\Laravel\Tests\TestCase;
use Lettermint\Laravel\Tests\WebhookRouteDisabledTestCase;

uses(TestCase::class)->in('Feature', 'Unit');
uses(WebhookRouteDisabledTestCase::class)->in('WebhookRoute');
