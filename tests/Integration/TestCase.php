<?php

namespace ChristianDarnell\Translation\Editor\Tests\Integration;

use ChristianDarnell\Translation\Editor\TranslationEditorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
	/**
	 * @param \Illuminate\Foundation\Application $app
	 *
	 * @return array
	 */
	protected function getPackageProviders($app)
	{
		return [
			TranslationEditorServiceProvider::class,
		];
	}
}
