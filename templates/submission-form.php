<?php
/**
 * The template for displaying the link submission form.
 *
 * This template can be overridden by copying it to
 * yourtheme/link-submission-pro/submission-form.php.
 */

if ( ! defined('WPINC') ) {
	die;
}

// Define the standard input classes to ensure consistency across all fields (including the dropdown)
$input_classes = 'w-full rounded-md border-slate-300 bg-white text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:focus:border-blue-500 dark:focus:ring-blue-500';
?>

<div id="lsp-form-wrapper" class="mx-auto max-w-2xl rounded-lg border border-slate-200 bg-white p-8 shadow-xl dark:border-slate-700 dark:bg-slate-800">
	<form id="lsp-submission-form" class="space-y-6">
		<h2 class="text-center text-2xl font-bold text-slate-900 dark:text-white">Submit a Link</h2>

		<div>
			<label for="lsp-url" class="block text-sm font-medium text-slate-700 dark:text-gray-300">Website URL</label>
			<div class="mt-1">
				<input type="url" name="lsp_url" id="lsp-url" required class="<?php echo $input_classes; ?>">
			</div>
		</div>

		<div>
			<label for="lsp-link-text" class="block text-sm font-medium text-slate-700 dark:text-gray-300">Link Text</label>
			<div class="mt-1">
				<input type="text" name="lsp_link_text" id="lsp-link-text" required class="<?php echo $input_classes; ?>">
			</div>
		</div>

		<div>
			<label for="lsp-user-name" class="block text-sm font-medium text-slate-700 dark:text-gray-300">Your Name</label>
			<div class="mt-1">
				<input type="text" name="lsp_user_name" id="lsp-user-name" required class="<?php echo $input_classes; ?>">
			</div>
		</div>

		<div>
			<label for="lsp-user-email" class="block text-sm font-medium text-slate-700 dark:text-gray-300">Your Email</label>
			<div class="mt-1">
				<input type="email" name="lsp_user_email" id="lsp-user-email" required class="<?php echo $input_classes; ?>">
			</div>
		</div>

		<div>
			<label for="lsp-category" class="block text-sm font-medium text-slate-700 dark:text-gray-300">Category</label>
			<div class="mt-1">
				<?php
				wp_dropdown_categories([
					'taxonomy'         => 'link_category',
					'name'             => 'lsp_category',
					'id'               => 'lsp-category',
					'show_option_none' => '— Select a Category —',
					'hide_empty'       => 0,
					'echo'             => 1,
					'class'            => $input_classes,
				]);
				?>
			</div>
		</div>

		<?php // A simple honeypot field for spam prevention. Bots will often fill this in. ?>
		<div class="hidden">
			<label for="lsp-website">Website</label>
			<input type="text" name="lsp_website" id="lsp-website" tabindex="-1" autocomplete="off">
		</div>

		<input type="hidden" name="action" value="lsp_submit_link">
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('lsp_submit_nonce')); ?>">

		<div>
			<button type="submit" id="lsp-submit-button" class="flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors duration-300 hover:bg-indigo-700 dark:bg-blue-600 dark:hover:bg-blue-700">
				Submit Link
			</button>
		</div>
	</form>

	<div id="lsp-form-messages" class="mt-4 text-center text-slate-600 dark:text-gray-400"></div>
</div>
