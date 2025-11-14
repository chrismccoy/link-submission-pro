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
?>
<div id="lsp-form-wrapper" class="bg-gray-800 p-8 rounded-lg shadow-xl max-w-2xl mx-auto">
	<form id="lsp-submission-form" class="space-y-6">
		<h2 class="text-2xl font-bold text-white text-center">Submit a Link</h2>
		<div>
			<label for="lsp-url" class="block text-sm font-medium text-gray-300">Website URL</label>
			<div class="mt-1"><input type="url" name="lsp_url" id="lsp-url" required class="bg-gray-700 text-white w-full border-gray-600 rounded-md shadow-sm"></div>
		</div>
		<div>
			<label for="lsp-link-text" class="block text-sm font-medium text-gray-300">Link Text</label>
			<div class="mt-1"><input type="text" name="lsp_link_text" id="lsp-link-text" required class="bg-gray-700 text-white w-full border-gray-600 rounded-md shadow-sm"></div>
		</div>
		<div>
			<label for="lsp-user-name" class="block text-sm font-medium text-gray-300">Your Name</label>
			<div class="mt-1"><input type="text" name="lsp_user_name" id="lsp-user-name" required class="bg-gray-700 text-white w-full border-gray-600 rounded-md shadow-sm"></div>
		</div>
		<div>
			<label for="lsp-user-email" class="block text-sm font-medium text-gray-300">Your Email</label>
			<div class="mt-1"><input type="email" name="lsp_user_email" id="lsp-user-email" required class="bg-gray-700 text-white w-full border-gray-600 rounded-md shadow-sm"></div>
		</div>
		<div>
			<label for="lsp-category" class="block text-sm font-medium text-gray-300">Category</label>
			<div class="mt-1">
				<?php
				wp_dropdown_categories([
					'taxonomy'         => 'link_category',
					'name'             => 'lsp_category',
					'id'               => 'lsp-category',
					'show_option_none' => '— Select a Category —',
					'hide_empty'       => 0,
					'echo'             => 1,
					'class'            => 'bg-gray-700 text-white w-full border-gray-600 rounded-md shadow-sm',
				]);
				?>
			</div>
		</div>
		<?php // A simple honeypot field for spam prevention. Bots will often fill this in. ?>
		<div style="display:none;"><label for="lsp-website">Website</label><input type="text" name="lsp_website" id="lsp-website" tabindex="-1" autocomplete="off"></div>
		<input type="hidden" name="action" value="lsp_submit_link">
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('lsp_submit_nonce')); ?>">
		<div><button type="submit" id="lsp-submit-button" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">Submit Link</button></div>
	</form>
	<div id="lsp-form-messages" class="mt-4 text-center"></div>
</div>
