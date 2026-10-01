/**
 * Cassette-CMF Core Fields JavaScript
 *
 * Default scripts for Cassette-CMF field types
 * Provides field validation, interactions, and enhancements
 *
 * @package PedalCMS\CassetteCMF
 * @since 1.0.0
 */

(function ($) {
	'use strict';

	/**
	 * Base Field Class
	 * All field classes extend from this
	 */
	class BaseField {
		constructor() {
			this.selector = '';
		}

		init() {
			// Override in child classes
		}

		showError($field, message) {
			const $wrapper = $field.closest('.cassette-cmf-field');
			$wrapper.addClass('has-error');
			$wrapper.find('.cassette-cmf-field-error').remove();
			$field.after('<span class="cassette-cmf-field-error">' + message + '</span>');
		}

		clearError($field) {
			const $wrapper = $field.closest('.cassette-cmf-field');
			$wrapper.removeClass('has-error');
			$wrapper.find('.cassette-cmf-field-error').remove();
		}

		debounce(func, wait) {
			let timeout;
			return function executedFunction(...args) {
				const later = () => {
					clearTimeout(timeout);
					func(...args);
				};
				clearTimeout(timeout);
				timeout = setTimeout(later, wait);
			};
		}
	}

	/**
	 * Color Picker Field
	 */
	class ColorField extends BaseField {
		constructor() {
			super();
			this.selector = '.cassette-cmf-color-picker, .cassette-cmf-field input[type="color"].use-wp-picker';
		}

		init() {
			if (typeof $.fn.wpColorPicker === 'undefined') {
				return;
			}

			$(this.selector).each(function () {
				const $input = $(this);

				// Skip if already initialized
				if ($input.hasClass('wp-color-picker')) {
					return;
				}

				$input.wpColorPicker({
					change: function (event, ui) {
						// Update the input value with the selected color
						$input.val(ui.color.toString());
						$input.trigger('change');
					},
					clear: function () {
						$input.val('');
						$input.trigger('change');
					}
				});
			});
		}
	}

	/**
	 * Email Field
	 */
	class EmailField extends BaseField {
		constructor() {
			super();
			this.selector = '.cassette-cmf-field input[type="email"]';
			this.emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		}

		init() {
			const self = this;
			$(this.selector).on('blur', function () {
				const email = $(this).val();
				if (email && !self.emailRegex.test(email)) {
					self.showError($(this), 'Please enter a valid email address.');
				} else {
					self.clearError($(this));
				}
			});
		}
	}

	/**
	 * URL Field
	 */
	class URLField extends BaseField {
		constructor() {
			super();
			this.selector = '.cassette-cmf-field input[type="url"]';
			this.urlRegex = /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/;
		}

		init() {
			const self = this;
			$(this.selector).on('blur', function () {
				const url = $(this).val();
				if (url && !self.urlRegex.test(url)) {
					self.showError($(this), 'Please enter a valid URL.');
				} else {
					self.clearError($(this));
				}
			});
		}
	}

	/**
	 * Number Field
	 */
	class NumberField extends BaseField {
		constructor() {
			super();
			this.selector = '.cassette-cmf-field input[type="number"]';
		}

		init() {
			const self = this;
			$(this.selector).each(function () {
				const $field = $(this);
				const min = parseFloat($field.attr('min')) || null;
				const max = parseFloat($field.attr('max')) || null;

				$field.on('change', function () {
					let value = parseFloat($(this).val());

					if (isNaN(value)) {
						return;
					}

					if (min !== null && value < min) {
						$(this).val(min);
						self.showError($(this), 'Value must be at least ' + min);
					} else if (max !== null && value > max) {
						$(this).val(max);
						self.showError($(this), 'Value must be at most ' + max);
					} else {
						self.clearError($(this));
					}
				});
			});
		}
	}

	/**
	 * Text/Textarea Field with Character Counter
	 */
	class TextFieldWithCounter extends BaseField {
		constructor() {
			super();
			this.selector = '.cassette-cmf-field input[maxlength], .cassette-cmf-field textarea[maxlength]';
		}

		init() {
			$(this.selector).each(function () {
				const $field = $(this);
				const maxLength = $field.attr('maxlength');
				const $counter = $('<span class="cassette-cmf-char-counter" style="display:block;margin-top:5px;color:#646970;font-size:12px;"></span>');

				$field.after($counter);

				const updateCounter = function () {
					const currentLength = $field.val().length;
					$counter.text(currentLength + ' / ' + maxLength + ' characters');

					if (currentLength >= maxLength) {
						$counter.css('color', '#d63638');
					} else {
						$counter.css('color', '#646970');
					}
				};

				updateCounter();
				$field.on('input', updateCounter);
			});
		}
	}

	/**
	 * Checkbox Group Field
	 */
	class CheckboxGroupField extends BaseField {
		constructor() {
			super();
			this.selector = '.cassette-cmf-field-checkbox-group';
		}

		init() {
			$(this.selector).each(function () {
				const $group = $(this);

				if ($group.data('checkbox-group-initialized')) {
					return;
				}
				$group.data('checkbox-group-initialized', true);

				const $checkboxes = $group.find('input[type="checkbox"]');

				// Add Select All/Deselect All button if more than 3 checkboxes
				if ($checkboxes.length > 3) {
					const allChecked = $checkboxes.filter(':checked').length === $checkboxes.length;
					const $selectAll = $('<button type="button" class="button button-small cassette-cmf-select-all" style="margin-bottom:10px;width:100px;display:inline-block;">' + (allChecked ? 'Deselect All' : 'Select All') + '</button>');
					$group.prepend($selectAll);

					$selectAll.on('click', function () {
						const allChecked = $checkboxes.filter(':checked').length === $checkboxes.length;

						if (allChecked) {
							$checkboxes.prop('checked', false);
							$(this).text('Select All');
						} else {
							$checkboxes.prop('checked', true);
							$(this).text('Deselect All');
						}
					});

					// Update button text on checkbox change
					$checkboxes.on('change', function () {
						const allChecked = $checkboxes.filter(':checked').length === $checkboxes.length;
						$selectAll.text(allChecked ? 'Deselect All' : 'Select All');
					});
				}
			});
		}
	}

	/**
	 * Tabs Field
	 */
	class TabsField extends BaseField {
		constructor() {
			super();
			this.selector = '.cassette-cmf-tabs';
		}

		init() {
			const self = this;

			$(this.selector).each(function () {
				const $tabsField = $(this);
				const $tablist = $tabsField.find('.cassette-cmf-tabs-nav');
				const $buttons = $tabsField.find('.cassette-cmf-tab-button');
				const $panels = $tabsField.find('.cassette-cmf-tab-panel');

				// Skip if already initialized
				if ($tabsField.data('tabs-initialized')) {
					return;
				}
				$tabsField.data('tabs-initialized', true);

				$buttons.on('click', function (e) {
					e.preventDefault();
					self.activateTab($tabsField, $buttons, $panels, $(this));
				});

				// Arrow keys move focus and activate (automatic-activation
				// tabs pattern); Home/End jump to the first/last tab.
				$tablist.on('keydown', '.cassette-cmf-tab-button', function (e) {
					const currentIndex = $buttons.index(this);
					let targetIndex = null;

					switch (e.key) {
						case 'ArrowRight':
						case 'ArrowDown':
							targetIndex = (currentIndex + 1) % $buttons.length;
							break;
						case 'ArrowLeft':
						case 'ArrowUp':
							targetIndex = (currentIndex - 1 + $buttons.length) % $buttons.length;
							break;
						case 'Home':
							targetIndex = 0;
							break;
						case 'End':
							targetIndex = $buttons.length - 1;
							break;
						default:
							return;
					}

					e.preventDefault();
					const $target = $buttons.eq(targetIndex);
					self.activateTab($tabsField, $buttons, $panels, $target);
					$target.trigger('focus');
				});

				// Activate first tab if none active
				if ($buttons.filter('.active').length === 0 && $buttons.length > 0) {
					self.activateTab($tabsField, $buttons, $panels, $buttons.first());
				} else {
					// Show active panel and sync ARIA state to match.
					$panels.hide();
					$panels.filter('.active').show().each(function () {
						$(document).trigger('cassette-cmf-tab-activated', [$(this)]);
					});
				}
			});
		}

		/**
		 * Activate a tab: update the active class, aria-selected, and the
		 * roving tabindex on every button, show the matching panel, and
		 * fire cassette-cmf-tab-activated (which the WYSIWYG field listens
		 * for to lazily initialize editors inside non-default tabs).
		 */
		activateTab($tabsField, $buttons, $panels, $button) {
			const targetId = $button.data('tab');
			const $targetPanel = $tabsField.find('.cassette-cmf-tab-panel[data-tab="' + targetId + '"]');

			$buttons.removeClass('active').attr({ 'aria-selected': 'false', tabindex: '-1' });
			$button.addClass('active').attr({ 'aria-selected': 'true', tabindex: '0' });

			$panels.removeClass('active').hide();
			$targetPanel.addClass('active').show();
			$(document).trigger('cassette-cmf-tab-activated', [$targetPanel]);
		}
	}

	/**
	 * WYSIWYG Field
	 */
	class WysiwygField extends BaseField {
		constructor() {
			super();
			this.selector = '.cassette-cmf-field-wysiwyg textarea.wp-editor-area';
		}

		init() {
			const self = this;

			$(this.selector).each(function () {
				const $textarea = $(this);

				self.initializeEditor($textarea);
				self.syncEditorHeight($textarea);
			});

			if (!$(document).data('cassette-cmf-wysiwyg-tab-handler')) {
				$(document).data('cassette-cmf-wysiwyg-tab-handler', true);
				$(document).on('cassette-cmf-tab-activated', function (event, $panel) {
					self.syncEditors($panel);
				});
			}
		}

		initializeEditor($textarea) {
			const editorId = $textarea.attr('id');

			if (!editorId) {
				return;
			}

			if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
				$textarea.data('cassette-cmf-wysiwyg-initialized', true);
				this.syncEditorHeight($textarea);
				return;
			}

			if ($textarea.hasClass('cassette-cmf-wysiwyg-editor') && typeof wp !== 'undefined' && wp.editor && typeof wp.editor.initialize === 'function') {
				wp.editor.initialize(editorId, this.getEditorSettings($textarea));
			}

			$textarea.data('cassette-cmf-wysiwyg-initialized', true);
			this.syncEditorHeight($textarea);
		}

		getEditorSettings($textarea) {
			const rawSettings = $textarea.attr('data-cassette-cmf-wysiwyg-settings');
			let fieldSettings = {};

			if (rawSettings) {
				try {
					fieldSettings = JSON.parse(rawSettings);
				} catch (error) {
					console.error('Error parsing WYSIWYG settings:', error);
				}
			}

			const defaultSettings = (typeof wp !== 'undefined' && wp.editor && typeof wp.editor.getDefaultSettings === 'function')
				? wp.editor.getDefaultSettings()
				: {};
			const settings = $.extend(true, {}, defaultSettings);

			settings.mediaButtons = fieldSettings.mediaButtons !== false;
			settings.textareaRows = parseInt(fieldSettings.textareaRows, 10) || 10;
			settings.textareaName = fieldSettings.textareaName || $textarea.attr('name');
			settings.editorClass = fieldSettings.editorClass || '';

			if (fieldSettings.quicktags === false) {
				settings.quicktags = false;
			} else if (settings.quicktags !== false) {
				settings.quicktags = $.extend(true, {}, settings.quicktags || {});
			}

			if (settings.tinymce !== false) {
				settings.tinymce = $.extend(true, {}, settings.tinymce || {});
				settings.tinymce.wpautop = fieldSettings.wpautop !== false;
				settings.tinymce.teeny = fieldSettings.teeny === true;
			}

			return settings;
		}

		syncEditors($container) {
			if (!$container || !$container.length) {
				return;
			}

			const self = this;
			$container.find(this.selector).each(function () {
				self.syncEditorHeight($(this));
			});
		}

		syncEditorHeight($textarea) {
			const editorId = $textarea.attr('id');
			const rowCount = parseInt($textarea.attr('rows'), 10) || 10;
			const lineHeight = parseInt($textarea.css('line-height'), 10) || 20;
			const editorHeight = rowCount * lineHeight;

			$textarea.css('height', editorHeight + 'px');

			if (!editorId || typeof tinymce === 'undefined') {
				return;
			}

			const editor = tinymce.get(editorId);

			if (!editor) {
				return;
			}

			const applyHeight = function () {
				const $container = $(editor.getContainer());
				$container.find('iframe').css('height', editorHeight + 'px');
				$container.find('.mce-edit-area, .tox-edit-area').css('min-height', editorHeight + 'px');
			};

			if (editor.initialized) {
				applyHeight();
			} else {
				editor.once('init', applyHeight);
			}
		}

		destroyEditors($container) {
			$container.find(this.selector).each(function () {
				const editorId = $(this).attr('id');

				if (!editorId) {
					return;
				}

				if (typeof wp !== 'undefined' && wp.editor && typeof wp.editor.remove === 'function') {
					wp.editor.remove(editorId);
				} else if (typeof tinymce !== 'undefined') {
					const editor = tinymce.get(editorId);
					if (editor) {
						editor.remove();
					}
				}
			});
		}
	}

	/**
	 * Repeater Field
	 */
	class RepeaterField extends BaseField {
		constructor() {
			super();
			this.selector = '.cassette-cmf-repeater';
			this.wysiwygField = new WysiwygField();
		}

		init() {
			const self = this;

			$(this.selector).each(function () {
				const $repeater = $(this);
				const $rows = $repeater.find('.cassette-cmf-repeater-rows');
				const $addButton = $repeater.find('.cassette-cmf-repeater-add');
				const $template = $repeater.find('script.cassette-cmf-repeater-template');
				const maxRows = parseInt($repeater.data('max-rows')) || 0;
				const minRows = parseInt($repeater.data('min-rows')) || 0;

				// Skip if already initialized
				if ($repeater.data('repeater-initialized')) {
					return;
				}
				$repeater.data('repeater-initialized', true);

				// Live-update a row's label from its row_label_field sub-field
				const rowLabelField = $repeater.data('row-label-field') || '';

				if (rowLabelField) {
					const debouncedLabelUpdate = self.debounce(function ($row) {
						const index = $rows.find('.cassette-cmf-repeater-row').index($row);
						self.updateRowLabelText($row, rowLabelField, index);
					}, 150);

					$repeater.on('input change', '.cassette-cmf-repeater-row-content :input', function () {
						debouncedLabelUpdate($(this).closest('.cassette-cmf-repeater-row'));
					});
				}

				// Add new row
				$addButton.on('click', function (e) {
					e.preventDefault();
					const rowCount = $rows.find('.cassette-cmf-repeater-row').length;

					if (maxRows && rowCount >= maxRows) {
						alert('Maximum number of rows reached.');
						return;
					}

					// Get template HTML and replace placeholders
					const templateHtml = $template.html();
					const newIndex = Date.now();
					const rowNumber = rowCount + 1;

					// Replace {{INDEX}} placeholder with unique index
					let newRowHtml = templateHtml.replace(/\{\{INDEX\}\}/g, newIndex);
					// Replace {{index}} placeholder in labels (1-based)
					newRowHtml = newRowHtml.replace(/\{\{index\}\}/g, rowNumber);

					const $newRow = $(newRowHtml);
					$rows.append($newRow);

					self.updateRowNumbers($repeater);
					self.checkMinMax($repeater);
					self.updateMoveButtonStates($repeater);

					// Trigger event for external scripts
					$(document).trigger('cassette-cmf-fields-added');
				});

				// Remove row - use event delegation
				$repeater.on('click', '.cassette-cmf-repeater-remove', function (e) {
					e.preventDefault();
					const rowCount = $rows.find('.cassette-cmf-repeater-row').length;

					if (minRows && rowCount <= minRows) {
						alert('Minimum number of rows required.');
						return;
					}

					const $row = $(this).closest('.cassette-cmf-repeater-row');
					self.wysiwygField.destroyEditors($row);

					$row.fadeOut(200, function() {
						$(this).remove();
						self.updateRowNumbers($repeater);
						self.checkMinMax($repeater);
						self.updateMoveButtonStates($repeater);
					});
				});

				// Toggle collapse - use event delegation with smooth animation
				$repeater.on('click', '.cassette-cmf-repeater-toggle', function (e) {
					e.preventDefault();
					e.stopPropagation();
					const $row = $(this).closest('.cassette-cmf-repeater-row');
					const $content = $row.find('> .cassette-cmf-repeater-row-content');
					const $icon = $(this).find('.dashicons');

					if ($row.hasClass('collapsed')) {
						$row.removeClass('collapsed');
						$content.slideDown(200);
						$icon.removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
					} else {
						$row.addClass('collapsed');
						$content.slideUp(200);
						$icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
					}
				});

				// Keyboard-accessible move up/down - an addition alongside
				// drag-and-drop, not a replacement. Independent of jQuery UI
				// Sortable, gated only on the same data-sortable flag.
				if ($repeater.data('sortable') !== false) {
					$repeater.on('click', '.cassette-cmf-repeater-move-up', function (e) {
						e.preventDefault();
						const $button = $(this);
						const $row = $button.closest('.cassette-cmf-repeater-row');
						const $prev = $row.prev('.cassette-cmf-repeater-row');

						if (!$prev.length) {
							return;
						}

						$row.insertBefore($prev);
						self.updateRowNumbers($repeater);
						self.updateMoveButtonStates($repeater);
						self.announceRowMove($repeater, $row);
						self.focusMoveButton($row, $button);
					});

					$repeater.on('click', '.cassette-cmf-repeater-move-down', function (e) {
						e.preventDefault();
						const $button = $(this);
						const $row = $button.closest('.cassette-cmf-repeater-row');
						const $next = $row.next('.cassette-cmf-repeater-row');

						if (!$next.length) {
							return;
						}

						$row.insertAfter($next);
						self.updateRowNumbers($repeater);
						self.updateMoveButtonStates($repeater);
						self.announceRowMove($repeater, $row);
						self.focusMoveButton($row, $button);
					});
				}

				// Make sortable
				if (typeof $.fn.sortable !== 'undefined' && $repeater.data('sortable') !== false) {
					$rows.sortable({
						handle: '.cassette-cmf-repeater-drag-handle',
						placeholder: 'ui-sortable-placeholder',
						start: function (e, ui) {
							ui.placeholder.height(ui.item.height());
						},
						stop: function () {
							self.updateRowNumbers($repeater);
							self.updateMoveButtonStates($repeater);
						}
					});
				}

				// Initial check
				self.checkMinMax($repeater);
				self.updateMoveButtonStates($repeater);
			});
		}

		updateRowNumbers($repeater) {
			const self = this;
			const rowLabelField = $repeater.data('row-label-field') || '';

			$repeater.find('.cassette-cmf-repeater-rows .cassette-cmf-repeater-row').each(function (index) {
				self.updateRowLabelText($(this), rowLabelField, index);
			});
		}

		/**
		 * Get the current value of a row's row_label_field sub-field, if any.
		 *
		 * Sub-field inputs are renamed to repeaterName[rowIndex][subFieldName]
		 * on render, so the target control is matched by that name suffix
		 * rather than by row index, which changes on add/remove/reorder.
		 */
		getRowLabelFieldValue($row, rowLabelField) {
			const suffix = '[' + rowLabelField + ']';
			let value = '';

			$row.find('> .cassette-cmf-repeater-row-content :input').each(function () {
				const name = $(this).attr('name') || '';
				if (name.slice(-suffix.length) === suffix) {
					value = $(this).val() || '';
					return false; // Stop at the first match.
				}
			});

			return value;
		}

		/**
		 * Set a row's label, preferring its row_label_field sub-field value
		 * (when configured and non-empty) over index-based "Row N" numbering.
		 */
		updateRowLabelText($row, rowLabelField, index) {
			const $label = $row.find('> .cassette-cmf-repeater-row-header .cassette-cmf-repeater-row-label').first();
			const fieldValue = rowLabelField ? this.getRowLabelFieldValue($row, rowLabelField) : '';

			$label.text(fieldValue ? fieldValue : 'Row ' + (index + 1));
		}

		/**
		 * Disable the first row's "move up" and the last row's "move down"
		 * button; enable all others. Call after any change to row count or
		 * order (add, remove, drag-sort, keyboard move).
		 */
		updateMoveButtonStates($repeater) {
			const $allRows = $repeater.find('.cassette-cmf-repeater-rows .cassette-cmf-repeater-row');
			const lastIndex = $allRows.length - 1;

			$allRows.each(function (index) {
				const $row = $(this);
				$row.find('> .cassette-cmf-repeater-row-header .cassette-cmf-repeater-move-up').prop('disabled', index === 0);
				$row.find('> .cassette-cmf-repeater-row-header .cassette-cmf-repeater-move-down').prop('disabled', index === lastIndex);
			});
		}

		/**
		 * Announce a row's new position via the repeater's polite live
		 * region, for screen reader users after a keyboard move.
		 */
		announceRowMove($repeater, $row) {
			const $announcer = $repeater.find('> .cassette-cmf-repeater-announcer');

			if (!$announcer.length) {
				return;
			}

			const $allRows = $repeater.find('.cassette-cmf-repeater-rows .cassette-cmf-repeater-row');
			const position = $allRows.index($row) + 1;
			const total = $allRows.length;

			$announcer.text('Row moved to position ' + position + ' of ' + total + '.');
		}

		/**
		 * Return focus to the button just used to move a row. If that
		 * button is now disabled (the row reached that end of the list),
		 * focus its sibling move button instead, which is guaranteed
		 * enabled unless the repeater has a single row.
		 */
		focusMoveButton($row, $clickedButton) {
			if (!$clickedButton.prop('disabled')) {
				$clickedButton.trigger('focus');
				return;
			}

			const isUp = $clickedButton.hasClass('cassette-cmf-repeater-move-up');
			const $sibling = $row.find(
				'> .cassette-cmf-repeater-row-header ' +
				(isUp ? '.cassette-cmf-repeater-move-down' : '.cassette-cmf-repeater-move-up')
			);

			if ($sibling.length && !$sibling.prop('disabled')) {
				$sibling.trigger('focus');
			}
		}

		checkMinMax($repeater) {
			const $rows = $repeater.find('.cassette-cmf-repeater-rows');
			const $addButton = $repeater.find('.cassette-cmf-repeater-add');
			const $removeButtons = $repeater.find('.cassette-cmf-repeater-remove');
			const rowCount = $rows.find('.cassette-cmf-repeater-row:not(.cassette-cmf-repeater-template)').length;
			const maxRows = parseInt($repeater.data('max-rows')) || 0;
			const minRows = parseInt($repeater.data('min-rows')) || 0;

			// Disable/enable add button
			if (maxRows && rowCount >= maxRows) {
				$addButton.prop('disabled', true);
			} else {
				$addButton.prop('disabled', false);
			}

			// Disable/enable remove buttons
			if (minRows && rowCount <= minRows) {
				$removeButtons.prop('disabled', true);
			} else {
				$removeButtons.prop('disabled', false);
			}
		}
	}

	/**
	 * Form Validation
	 */
	class FormValidation extends BaseField {
		constructor() {
			super();
		}

		init() {
			const self = this;

			$('form').off('submit.cassette-cmf-validation').on('submit.cassette-cmf-validation', function (e) {
				let hasErrors = false;

				$(this).find('.cassette-cmf-field [required]').each(function () {
					const $field = $(this);
					const $wrapper = $field.closest('.cassette-cmf-field');
					const fieldType = $field.attr('type');
					let isEmpty = false;

					if ($wrapper.hasClass('cassette-cmf-hidden')) {
						self.clearError($field);
						return;
					}

					if (fieldType === 'checkbox' || fieldType === 'radio') {
						const name = $field.attr('name');
						isEmpty = !$('input[name="' + name + '"]:checked').length;
					} else {
						isEmpty = !$field.val() || $field.val().trim() === '';
					}

					if (isEmpty) {
						self.showError($field, 'This field is required.');
						hasErrors = true;
					}
				});

				if (hasErrors) {
					e.preventDefault();
					// Scroll to first error
					$('html, body').animate({
						scrollTop: $('.cassette-cmf-field.has-error:first').offset().top - 100
					}, 300);
				}
			});
		}
	}

	/**
	 * Conditional Fields
	 */
	class ConditionalFields extends BaseField {
		constructor() {
			super();
			this.selector = '[data-conditional]';
		}

		init() {
			const controllerDependencies = new Map();

			this.clearControllerEvents();

			$(this.selector).each((index, element) => {
				const $field = $(element);
				const conditional = this.getConditionalConfig($field);

				if (!conditional || !Array.isArray(conditional.rules) || !conditional.rules.length) {
					return;
				}

				this.collectControllerDependencies($field, conditional, controllerDependencies);
				this.evaluateVisibility($field);
			});

			this.bindControllerEvents(controllerDependencies);
		}

		clearControllerEvents() {
			$('input[name], select[name], textarea[name]').off('.cassette-cmf-conditional');
		}

		collectControllerDependencies($field, conditional, controllerDependencies) {
			const boundNames = [];

			conditional.rules.forEach((rule) => {
				if (!rule.field || boundNames.includes(rule.field)) {
					return;
				}

				boundNames.push(rule.field);
				const $controllers = this.findControllerFields($field, rule.field);

				$controllers.each((index, element) => {
					if (!controllerDependencies.has(element)) {
						controllerDependencies.set(element, new Set());
					}

					controllerDependencies.get(element).add($field.get(0));
				});
			});
		}

		bindControllerEvents(controllerDependencies) {
			controllerDependencies.forEach((dependentFields, controller) => {
				$(controller).on('change.cassette-cmf-conditional input.cassette-cmf-conditional', () => {
					dependentFields.forEach((field) => {
						this.evaluateVisibility($(field));
					});
				});
			});
		}

		getConditionalConfig($field) {
			const conditionalAttr = $field.attr('data-conditional');

			if (conditionalAttr) {
				return this.parseConditionalJson(conditionalAttr);
			}

			return null;
		}

		parseConditionalJson(value) {
			if (!value) {
				return null;
			}

			if (typeof value === 'object') {
				return value;
			}

			try {
				return JSON.parse(value);
			} catch (error) {
				console.error('Invalid conditional configuration:', error);
				return null;
			}
		}

		findControllerFields($field, fieldName) {
			const $rowScope = $field.closest('.cassette-cmf-repeater-row');
			const $formScope = $field.closest('form');
			const scopes = [];

			if ($rowScope.length) {
				scopes.push($rowScope);
			}

			if ($formScope.length) {
				scopes.push($formScope);
			}

			scopes.push($(document));

			for (let index = 0; index < scopes.length; index++) {
				const $scope = scopes[index];
				const $matches = $scope.find('input[name], select[name], textarea[name]').filter((matchIndex, element) => {
					const name = $(element).attr('name') || '';
					return this.matchesFieldName(name, fieldName);
				});

				if ($matches.length) {
					return this.normalizeControllerFields($matches);
				}
			}

			return $();
		}

		matchesFieldName(name, fieldName) {
			return name === fieldName
				|| name === fieldName + '[]'
				|| name.endsWith('_' + fieldName)
				|| name.endsWith('_' + fieldName + '[]')
				|| name.endsWith('[' + fieldName + ']')
				|| name.endsWith('[' + fieldName + '][]');
		}

		normalizeControllerFields($controllers) {
			if (!$controllers.length) {
				return $controllers;
			}

			const $nonHidden = $controllers.filter((index, element) => (($(element).attr('type') || '').toLowerCase() !== 'hidden'));

			return $nonHidden.length ? $nonHidden : $controllers;
		}

		evaluateVisibility($field) {
			const conditional = this.getConditionalConfig($field);

			if (!conditional || !Array.isArray(conditional.rules) || !conditional.rules.length) {
				this.toggleField($field, true);
				return;
			}

			const relation = conditional.relation === 'OR' ? 'OR' : 'AND';
			const results = conditional.rules.map((rule) => {
				const $controllers = this.findControllerFields($field, rule.field);
				const currentValue = this.getFieldValue($controllers);
				return this.evaluateRule(rule, currentValue);
			});

			const shouldShow = relation === 'OR'
				? results.some(Boolean)
				: results.every(Boolean);

			this.toggleField($field, shouldShow);
		}

		getFieldValue($controllers) {
			if (!$controllers.length) {
				return '';
			}

			const $first = $controllers.first();
			const tagName = ($first.prop('tagName') || '').toLowerCase();
			const type = ($first.attr('type') || '').toLowerCase();

			if (type === 'radio') {
				const $checked = $controllers.filter(':checked').first();
				return $checked.length ? $checked.val() : '';
			}

			if (type === 'checkbox') {
				if ($controllers.length === 1) {
					return $first.is(':checked') ? ($first.val() || '1') : '0';
				}

				return $controllers.filter(':checked').map((index, element) => $(element).val()).get();
			}

			if (tagName === 'select' && $first.prop('multiple')) {
				return $first.val() || [];
			}

			return $first.val();
		}

		evaluateRule(rule, currentValue) {
			const operator = rule.operator || '==';
			const expectedValue = Object.prototype.hasOwnProperty.call(rule, 'value') ? rule.value : null;

			switch (operator) {
				case 'empty':
					return this.isEmptyValue(currentValue);
				case 'not_empty':
					return !this.isEmptyValue(currentValue);
				case 'in':
					return this.matchesAny(currentValue, Array.isArray(expectedValue) ? expectedValue : [expectedValue]);
				case 'not_in':
					return !this.matchesAny(currentValue, Array.isArray(expectedValue) ? expectedValue : [expectedValue]);
				case '>':
				case '>=':
				case '<':
				case '<=':
					return this.evaluateNumericRule(currentValue, expectedValue, operator);
				case '!=':
					return !this.matchesValue(currentValue, expectedValue);
				case '==':
				default:
					return this.matchesValue(currentValue, expectedValue);
			}
		}

		toggleField($field, shouldShow) {
			const $visibilityContainer = this.getVisibilityContainer($field);

			$field.toggleClass('cassette-cmf-hidden', !shouldShow);
			$field.attr('aria-hidden', shouldShow ? 'false' : 'true');

			if ($visibilityContainer.length && !$visibilityContainer.is($field)) {
				$visibilityContainer.toggleClass('cassette-cmf-hidden', !shouldShow);
				$visibilityContainer.attr('aria-hidden', shouldShow ? 'false' : 'true');
			}

			$field.find('[required]').each((index, element) => {
				const $element = $(element);

				if (shouldShow) {
					if ($element.data('cassette-required')) {
						$element.prop('required', true);
						$element.removeData('cassette-required');
					}
					return;
				}

				$element.data('cassette-required', true);
				$element.prop('required', false);
			});
		}

		getVisibilityContainer($field) {
			const $row = $field.closest('tr');

			if ($row.length && $row.children('th, td').length >= 2) {
				return $row;
			}

			return $field;
		}

		matchesAny(currentValue, expectedValues) {
			if (Array.isArray(currentValue)) {
				return currentValue.some((value) => this.matchesAny(value, expectedValues));
			}

			return expectedValues.some((expectedValue) => this.matchesValue(currentValue, expectedValue));
		}

		matchesValue(currentValue, expectedValue) {
			if (Array.isArray(currentValue)) {
				return currentValue.some((value) => this.matchesValue(value, expectedValue));
			}

			return String(currentValue) === String(expectedValue);
		}

		evaluateNumericRule(currentValue, expectedValue, operator) {
			if (Array.isArray(currentValue)) {
				currentValue = currentValue.length ? currentValue[0] : '';
			}

			if (currentValue === '' || expectedValue === '' || isNaN(Number(currentValue)) || isNaN(Number(expectedValue))) {
				return false;
			}

			const actual = Number(currentValue);
			const expected = Number(expectedValue);

			switch (operator) {
				case '>':
					return actual > expected;
				case '>=':
					return actual >= expected;
				case '<':
					return actual < expected;
				case '<=':
					return actual <= expected;
				default:
					return false;
			}
		}

		isEmptyValue(value) {
			if (Array.isArray(value)) {
				return value.filter((entry) => !this.isEmptyValue(entry)).length === 0;
			}

			return value === null || typeof value === 'undefined' || value === '';
		}
	}

	/**
	 * Main Cassette-CMF Fields Manager
	 */
	const CassetteCMFFields = {
		fields: [],

		init: function () {
			// Initialize all field classes
			this.fields = [
				new ColorField(),
				new EmailField(),
				new URLField(),
				new NumberField(),
				new TextFieldWithCounter(),
				new CheckboxGroupField(),
				new TabsField(),
				new WysiwygField(),
				new RepeaterField(),
				new FormValidation(),
				new ConditionalFields()
			];

			// Initialize each field
			this.fields.forEach(field => {
				try {
					field.init();
				} catch (error) {
					console.error('Error initializing field:', error);
				}
			});
		},

		reinit: function () {
			// Reinitialize all fields (useful after AJAX)
			this.init();
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function () {
		CassetteCMFFields.init();
	});

	/**
	 * Reinitialize after AJAX (for dynamic field additions)
	 */
	$(document).on('cassette-cmf-fields-added', function () {
		CassetteCMFFields.reinit();
	});

	// Expose to global scope for external access
	window.CassetteCMFFields = CassetteCMFFields;

})(jQuery);
