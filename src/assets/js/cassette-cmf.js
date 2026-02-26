/**
 * Cassette-CMF Core Fields JavaScript
 *
 * Default scripts for Cassette-CMF field types
 * Provides field validation, interactions, and enhancements
 *
 * @package Pedalcms\WpCmf
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
				const $checkboxes = $group.find('input[type="checkbox"]');

				// Add Select All/Deselect All button if more than 3 checkboxes
				if ($checkboxes.length > 3) {
					const $selectAll = $('<button type="button" class="button button-small cassette-cmf-select-all" style="margin-bottom:10px;width:100px;display:inline-block;">Select All</button>');
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
			$(this.selector).each(function () {
				const $tabs = $(this);
				const $buttons = $tabs.find('.cassette-cmf-tab-button');
				const $panels = $tabs.find('.cassette-cmf-tab-panel');

				// Skip if already initialized
				if ($tabs.data('tabs-initialized')) {
					return;
				}
				$tabs.data('tabs-initialized', true);

				$buttons.on('click', function (e) {
					e.preventDefault();
					const targetId = $(this).data('tab');

					// Update buttons
					$buttons.removeClass('active');
					$(this).addClass('active');

					// Update panels - use data-tab attribute selector
					$panels.removeClass('active').hide();
					$tabs.find('.cassette-cmf-tab-panel[data-tab="' + targetId + '"]').addClass('active').show();
				});

				// Activate first tab if none active
				if ($buttons.filter('.active').length === 0 && $buttons.length > 0) {
					$buttons.first().trigger('click');
				} else {
					// Show active panel
					$panels.hide();
					$panels.filter('.active').show();
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

					$(this).closest('.cassette-cmf-repeater-row').fadeOut(200, function() {
						$(this).remove();
						self.updateRowNumbers($repeater);
						self.checkMinMax($repeater);
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
						}
					});
				}

				// Initial check
				self.checkMinMax($repeater);
			});
		}

		updateRowNumbers($repeater) {
			$repeater.find('.cassette-cmf-repeater-rows .cassette-cmf-repeater-row').each(function (index) {
				$(this).find('> .cassette-cmf-repeater-row-header .cassette-cmf-repeater-row-label').first().text('Row ' + (index + 1));
			});
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

			$('form').on('submit', function (e) {
				let hasErrors = false;

				$(this).find('.cassette-cmf-field [required]').each(function () {
					const $field = $(this);
					const fieldType = $field.attr('type');
					let isEmpty = false;

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
			this.selector = '[data-show-if]';
		}

		init() {
			$(this.selector).each(function () {
				const $field = $(this);
				const showIfData = $field.data('show-if');

				if (typeof showIfData === 'object') {
					const targetField = showIfData.field;
					const targetValue = showIfData.value;
					const $targetField = $('[name="' + targetField + '"]');

					const checkVisibility = function () {
						const currentValue = $targetField.val();
						if (currentValue == targetValue) {
							$field.show();
						} else {
							$field.hide();
						}
					};

					checkVisibility();
					$targetField.on('change', checkVisibility);
				}
			});
		}
	}

	/**
	 * Main Cassette-CMF Fields Manager
	 */
	const CassetteCmfFields = {
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
		CassetteCmfFields.init();
	});

	/**
	 * Reinitialize after AJAX (for dynamic field additions)
	 */
	$(document).on('cassette-cmf-fields-added', function () {
		CassetteCmfFields.reinit();
	});

	// Expose to global scope for external access
	window.CassetteCmfFields = CassetteCmfFields;

})(jQuery);
