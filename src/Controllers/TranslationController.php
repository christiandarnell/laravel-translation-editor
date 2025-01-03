<?php

namespace ChristianDarnell\Translation\Editor\Controllers;

use ChristianDarnell\Translation\Editor\TranslationEditor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TranslationController extends Controller
{
	/**
	 * @var \ChristianDarnell\Translation\Editor\TranslationEditor
	 */
	protected $translationEditor;

	/**
	 * @param \ChristianDarnell\Translation\Editor\TranslationEditor $translationEditor
	 */
	public function __construct(TranslationEditor $translationEditor)
	{
		$this->translationEditor = $translationEditor;
	}

	/**
	 * @param \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function show(Request $request)
	{
		$locale = $request->get('locale');
		$path   = $request->get('path');

		$translation = $this->translationEditor->retrieveTranslation($path, $locale);

		return response()->json($translation);
	}

	/**
	 * @param \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function store(Request $request)
	{
		$locale      = $request->get('locale');
		$path        = $request->get('path');
		$translation = $request->get('translation');

		$this->translationEditor->storeTranslation($path, $translation, $locale);

		// Compile the translation for rendering
		return response()->json([
			'compiled' => $translation,
		]);
	}
}
