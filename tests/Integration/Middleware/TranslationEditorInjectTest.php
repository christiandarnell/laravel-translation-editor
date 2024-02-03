<?php

namespace ChristianDarnell\Translation\Editor\Tests\Unit\Middleware;

use ChristianDarnell\Translation\Editor\Middleware\TranslationEditorInject;
use ChristianDarnell\Translation\Editor\Tests\Integration\TestCase;
use ChristianDarnell\Translation\Editor\TranslationEditor;
use Illuminate\Http\Request;
use Mockery as m;
use Symfony\Component\HttpFoundation\Response;

class TranslationEditorInjectTest extends TestCase
{
	/**
	 * @var \ChristianDarnell\Translation\Editor\Middleware\TranslationEditorInject
	 */
	protected $translationEditorInject;

	/**
	 * @var \ChristianDarnell\Translation\Editor\TranslationEditor|\Mockery\LegacyMockInterface|\Mockery\MockInterface
	 */
	protected $translationEditor;

	public function setUp(): void
	{
		parent::setUp();
		$this->translationEditor = m::mock(TranslationEditor::class);
		$this->translationEditorInject = new TranslationEditorInject($this->translationEditor);
	}

	/**
	 * @test
	 * @return void
	 * @throws \Exception
	 */
	public function testHandleInjected(): void
	{
		$request = new Request();

		$this->translationEditor->shouldReceive('isEnabled')->once()->andReturn(true);

		$response = $this->translationEditorInject->handle($request, function ($req) {
			return new Response('</translation-editor></head>', 200, [
				'Content-Type' => 'html'
			]);
		});
		$this->assertStringContainsString('<script', $response->getContent());
		$this->assertStringContainsString('<link', $response->getContent());
	}

	/**
	 * @test
	 * @return void
	 * @throws \Exception
	 */
	public function testHandleShouldNotInject(): void
	{
		$request = new Request();

		$this->translationEditor->shouldReceive('isEnabled')->once()->andReturn(false);

		$response = $this->translationEditorInject->handle($request, function ($req) {
			return new Response('', 200, []);
		});
		$this->assertStringNotContainsString('<script', $response->getContent());
		$this->assertStringNotContainsString('<link', $response->getContent());
	}
}
