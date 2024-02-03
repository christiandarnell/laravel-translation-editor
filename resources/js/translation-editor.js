(function () {
	var $target = null;

	var editor = function () {
		if (!document.getElementById('translation-editor')) {
			var editorElement = document.createElement('div');

			editorElement.id = 'translation-editor';
			editorElement.classList.add('translation-editor__modal');
			editorElement.innerHTML = '<div class="translation-editor__dialog">' +
				'<form id="translation-editor-form" class="translation-editor__body">' +
				'<input type="hidden" name="locale" id="translation-editor-locale" />' +
				'<input type="hidden" name="path" id="translation-editor-path" />' +
				'<label for="translation-editor-source">Source text (<span id="translation-editor-source-locale" class="translation-editor__locale"></span>):</label>' +
				'<textarea id="translation-editor-source" rows="6" readonly></textarea>' +
				'<label for="translation-editor-translation">Translation (<span id="translation-editor-destination-locale" class="translation-editor__locale"></span>):</label>' +
				'<textarea id="translation-editor-destination-translation" rows="6" name="translation"></textarea>' +
				'</form>' +
				'<div class="translation-editor__footer">' +
				'<div class="translation-editor__path">Key: <span id="translation-editor-path-label"></span></div>' +
				'<div>' +
				'<button type="button" class="translation-editor__default">Cancel</button> ' +
				'<button type="button" class="translation-editor__primary">Save (Ctrl + Enter)</button>' +
				'</div>' +
				'</div>' +
				'</div>';

			editorElement.querySelector('.translation-editor__default').addEventListener('click', function () {
				editor().classList.remove('in');
			});

			editorElement.querySelector('.translation-editor__primary').addEventListener('click', function () {
				save();
			});

			editorElement.querySelector('.translation-editor__dialog').addEventListener('click', function (e) {
				e.stopPropagation();
			});

			document.body.appendChild(editorElement);
		}

		return document.getElementById('translation-editor');
	};

	class TranslationEditor extends HTMLElement {
		constructor() {
			super();

			this.addEventListener('click', function (e) {
				if (!e.ctrlKey) {
					return;
				}

				e.preventDefault();
				e.stopPropagation();

				$target = e.target;

				var locale = $target.getAttribute('locale'),
					path = $target.getAttribute('path');

				editor().classList.add('in');

				document.getElementById('translation-editor-source').value = 'Loading...';
				document.getElementById('translation-editor-destination-translation').value = 'Loading...';

				window.fetch(encodeURI('{baseRoute}/translation?locale=' + locale + '&path=' + path))
					.then(function (response) {
						return response.json();
					})
					.then(function (json) {
						document.getElementById('translation-editor-locale').value = locale;
						document.getElementById('translation-editor-path').value = path;
						document.getElementById('translation-editor-path-label').innerHTML = path;

						document.getElementById('translation-editor-source-locale').innerHTML = json.source.locale;
						document.getElementById('translation-editor-source').value = json.source.translation;

						document.getElementById('translation-editor-destination-locale').innerHTML = json.destination.locale;
						var translation = document.getElementById('translation-editor-destination-translation');

						translation.value = json.destination.translation;
						translation.focus();
					});
			});
		}
	}

	customElements.define('translation-editor', TranslationEditor);

	var toggleHighlight = function (e) {
		document.body.classList.toggle('translation-editor__highlight', e.ctrlKey);
		if (e.ctrlKey && e.key === "Enter") {
			save();
		}
	};

	function save() {
		var form = new FormData(document.getElementById('translation-editor-form'));

		window.fetch('{baseRoute}/translation', {
			method: 'POST',
			body: form
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				$target.innerHTML = json.compiled;

				editor().classList.remove('in');
			});
		location.reload();
	}

	document.addEventListener('keydown', toggleHighlight);
	document.addEventListener('keyup', toggleHighlight);
})();
