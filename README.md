# Cornerstone Versioning
This plugin provides you an environment for each page to create cornerstone revisions.
It'll create for any saving process a new revision. You can restore/delete this one inside the edit page of the page.
These revisions include the following settings
- The post content
- The '_cornerstone_data' meta key/value
- The '_cornerstone_settings' meta key/value

# Compatibility
This plugin is compatible with all available Cornerstone, X, PRO versions

# Howto use it
1. Install the plugin as usual
2. Save your cornerstone pages
3. Under the backend edit page you'll find a custom meta box called **Cornerstone Revisions**

# Custom Hooks
Also it provides a bunch of custom hooks
```sh
cv_main_capability | string | The main capability oth this plugin
```
```sh
cv_allowed_post_types | array | An array of all available post types
```
```sh
cv_version_prefix | string | Filter the metakey prefix
```
```sh
cv_filter_version_name | string | Filter the whole metakey name
```
```sh
cv_is_user_able_to | bool | True if the user is able to edit the current post versioning
```
```sh
cornerstone_store_as_json | bool | Already known from Cornerstone|Pro|X
```
```sh
cv_edit_custom_saveable_array | array | Parse your custom saving values to the array
```
```sh
cv_allowed_post_types | array | An array of all available post types
```
```sh
cv_allowed_post_types | array | An array of all available post types
```
```sh
cv_after_restoring_data | ACTION | Fires after restoring the custom version
```
