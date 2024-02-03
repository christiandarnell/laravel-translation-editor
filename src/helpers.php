<?php
if (!function_exists('__te')) {
	function __te($key, array $replace = [], $locale = null)
	{
		return app('translation.editor')->get($key, $replace, $locale);
	}
}
