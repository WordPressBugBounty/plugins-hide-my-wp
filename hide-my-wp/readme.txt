=== Hide My WP Ghost - Security & Firewall ===
Contributors: johndarrel
Tags: security,firewall,brute force,login,hide my wp
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 7.0.15
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Hide My WP Ghost is a WordPress security plugin that hides WP paths and protects your site with Path Security, Firewall, Brute Force Protection, 2FA

== Description ==

**WP Ghost (formerly Hide My WP Ghost)** is a **WordPress security plugin focused on hack prevention** that changes and secures default paths, **filters requests through the 9G Firewall**, and **helps block automated scanners probing default WordPress URLs**.

Paths Security changes public URLs such as `wp-login.php` and restricts access to their default addresses. WP Ghost also includes two-factor authentication (2FA) and a Security Check with tasks for your site's settings.

= WordPress security problems WP Ghost can help with =

* **Repeated failed logins**: set attempt limits and lockout durations under Brute Force protection.
* Bots requesting `wp-login.php`: use a custom login URL and block the default address.
* **Visible plugin folders**: change the public paths used in your site's HTML.
* **Suspicious requests**: use the 9G Firewall to filter supported attack patterns.
* **Unclear security settings**: run Security Check and review its prioritized tasks.
* **Repeated WooCommerce payment attempts**: set checkout attempt limits for each IP address.

WP Ghost focuses on attack prevention and WordPress hardening. Keep WordPress and its plugins updated, maintain backups, and use malware scanning when you need to check for infected files.

[youtube https://youtu.be/QMdoSN8dk1c]

**Official websites:** <a href="https://wpghost.com">WP Ghost</a> and <a href="https://hidemywpghost.com">Hide My WP Ghost</a>.

= How Paths Security changes public URLs =

WP Ghost **changes and secures over 30 default WordPress paths**, including `wp-admin`, `wp-login.php`, `wp-content`, `wp-includes`, `admin-ajax.php`, `/wp-json`, plugin directories, and theme folders.

Paths Security rewrites public routes and can block requests to their old addresses. Changing a URL does not fix vulnerable plugin code or replace login authentication.

= 9G Firewall in version 7.0.15 =

Version 7.0.15 adds the 9G Firewall as the default filter on new installs. Choose it under **WP Ghost > Firewall & Headers > Firewall Strength**.

WP Ghost uses server rules and application checks, depending on the request and configuration. Test forms and checkout after changing firewall settings.

= What the 9G Firewall checks =

* **SQL injection, cross-site scripting (XSS), directory traversal, and scanner probes** in URLs and query strings
* **Unauthenticated exploit payloads** sent to `admin-ajax.php` and the `/wp-json` REST API
* **File uploads containing PHP shells** or scripts disguised as images
* **Visitor comment and form submissions** on firewall levels 3 and 4
* **Privilege-escalation attempts** on user registration endpoints
* **PHP object injection and Server-Side Request Forgery (SSRF)** requests

Read the <a href="https://wpghost.com/kb/9g-firewall/">9G Firewall documentation</a>.

= Ghost Doctor path checks and repairs =

Nginx needs rewrite rules outside `.htaccess`. Apache and managed hosts can also restrict rewrite settings. Cached CSS URLs can leave a page unstyled after path changes.

**Ghost Doctor** runs from **WP Ghost > Level of Security** and tests the site the way a logged-out visitor loads it. It checks the homepage HTML, theme CSS and JS files, `admin-ajax.php`, the REST API, and the custom admin path.

= How Ghost Doctor repairs a broken path =

* **Reads your server type** (Nginx, Apache, LiteSpeed, or IIS), checks whether rewrite rules are written to the server config file, and detects active cache plugins
* **Applies repairs from least invasive to fallback**, such as serving CSS and JS through WordPress while a cache clears or switching to a mode that needs no server rewrites
* **Tests each repair right after applying it and reverts the change if the check still fails**
* **Saves a configuration snapshot before changing anything**, so clicking **Undo All Changes** restores your previous setup
* **Links to the exact hosting panel guide** when a step requires a server reload outside WordPress

Read the <a href="https://wpghost.com/kb/ghost-doctor/">Ghost Doctor guide</a> and the <a href="https://wpghost.com/kb/wp-ghost-paths-not-working-ghost-doctor/">troubleshooting steps</a>.

= AI Security Explanations =

Security Check runs locally and provides a free score from 0 to 100, with prioritized tasks.

Clicking **AI Security Explanations** sends those findings and setup details to generate a report for your configuration.

**AI Security Explanations require a connected WP Ghost account with an active subscription** and use a **monthly allowance of checks**.

= What the report returns =

* **One summary paragraph** at the top answering whether your current configuration is exposed
* **Plain headlines** that replace internal task names with the actual risk on your server
* **One instruction per finding** telling you which setting or file to change
* **An on-demand Details panel** explaining what an attacker can do with that finding on your server type
* **A re-ordered task list** ranked by what exposes your site right now

Only the findings on your Security Check screen and six setup parameters (server type, config writability, rewrite presence, cache plugin presence, security mode, and mapped file count) are sent. This AI request excludes page content, user records, passwords and database rows. Premium cloud event retention is a separate feature.

= Requests and activity covered by WP Ghost controls =

* **Brute force login and credential stuffing** on `wp-login.php`, custom login forms, WooCommerce accounts, and REST API Application Passwords
* **SQL injection (SQLi) and cross-site scripting (XSS)** in URLs, query strings, and form submissions
* Plugin and theme probes that rely on default `/wp-content/plugins/` folder names
* **XML-RPC pingback amplification** and unauthenticated `/wp-json` user scraping
* **Automated bot reconnaissance** looking for WordPress version tags, generator meta tags, and source map comments
* **WooCommerce checkout card testing** from repeated IP requests

= Features in the free plugin =

= 1. Change and secure paths (Paths Security) =

* Change `wp-admin` and `wp-login.php` to custom names and return a 404 or custom redirect on the default URLs
* Change the **lost password, registration, and logout paths**
* Rename `wp-content`, `wp-includes`, and the uploads path in public HTML
* Change `admin-ajax.php` and the `/wp-json` REST API route
* **Activate** protection in one click with **3 security presets**: Minimal, Lite Mode + Firewall, or Lite Mode + Firewall + Brute Force + Logs + 2FA
* Verify your homepage, theme assets, and API routes with **Frontend Check and Ghost Doctor** before saving path changes
* Set **role-based login and logout redirect URLs**
* Style your custom login URL with the **Login Page Designer** using a custom logo, background, 12 layouts, and 10 color schemes

= 2. 9G Firewall and login security =

* Filter requests with the **9G, 8G, or 7G Firewall rules**
* Log in without passwords using **Passkey 2FA** (Face ID, Touch ID, Windows Hello, or hardware keys)
* Enable Authenticator app codes, email 2FA codes, backup codes, browser trust, and **forced 2FA setup by user role**
* Send **HTTP security headers:** Content-Security-Policy (CSP), HSTS, X-Frame-Options, and X-Content-Type-Options
* Block specific **IP addresses, User-Agents, and referrers**
* View blocked requests in the **Security Threats Log** with password and payment fields masked
* Track logins, role updates, and admin actions in the **User Events Log**
* Check the top 5 attack origin countries on the **Overview GEO Threats Map**
* Track your **0 to 100 Security Optimization Score** on the Overview and Security Check screens
* Create expiring **Temporary Logins** and one-time **Magic Login** email links capped at the creator's permission level

= 3. Footprint removal =

* Strip **WordPress version numbers and meta generator tags** from the HTML head
* Remove **source map references** inside inline scripts and styles and strip HTML comments
* Block `?author=1` queries and **unauthenticated REST API user enumeration**
* Hide the **top admin toolbar** on the frontend for selected roles
* Disable `wp-emoji` scripts, RSD links, and `wp-admin` prefetch hints

= 4. Access and endpoint controls =

* Disable `xmlrpc.php` requests when your site does not need XML-RPC integrations
* Restrict `/wp-json` REST API access to logged-in users; test integrations that use public endpoints
* Disable right-click and selected copy shortcuts on public pages; these controls do not prevent downloading page content
* Turn off **directory browsing** across WordPress folders

= 5. Brute force and checkout protection =

* Add **Google reCAPTCHA v2, v3, Enterprise**, or **one-time tokenized Math CAPTCHA** to login and signup forms, with a built-in key tester
* Protect standard logins, Elementor and page-builder login widgets, and WooCommerce login screens
* Limit repeated **WooCommerce checkout submissions and failed payment attempts** from the same IP address
* Set **custom attempt thresholds, lockout durations, and IP whitelists**

= 6. Diagnostics and extra tools =

* Repair broken path or cache setups with **Ghost Doctor** and revert any test using saved snapshots
* Work through a single **prioritized Security Check task list** grouped into action bands like "worth fixing this week"
* Rewrite specific CSS class names, IDs, or URLs in page output with **Text & URL Mapping**
* Log in with one click from **Softaculous** (cPanel, Plesk, DirectAdmin, Webuzo, CentOS Web Panel) and **aaPanel WP Toolkit**

= WP Ghost Premium features =

* **Ghost Mode preset:** turns on all path changes, hides file extensions, and enables full footprint removal in one click
* Monthly allowance of **AI Security Explanations** included with your subscription
* **Automated IP blocking** when an address triggers repeated firewall or brute-force blocks
* **AI Copyright Protection:** crawler controls at the firewall and in `robots.txt`, including rules for `GPTBot`, `ClaudeBot`, `PerplexityBot`, `CCBot` and `Bytespider`
* **Unlimited Security Threats Log and User Events Log entries** with country, status, and threat filters, search, and CSV export
* **30-day cloud event retention** for audit history
* **Instant email alerts** on brute-force lockouts or disabled logs, plus optional weekly threat summary emails
* **Country blocking (Geo-Security)** for the whole site or specific paths
* **File extension hiding** for `.php`, `.css`, `.js`, and `.json` URLs
* **One-click database prefix rename, file permission repair, and SALT key regeneration**

PerplexityBot supports AI search discovery, not foundation model training. Review crawler rules before enabling **AI Copyright Protection**; blocking a search crawler can limit visibility in that service.

See all <a href="https://wpghost.com/features/">WP Ghost Premium features</a>.

== Technical compatibility ==

* **Hosting environments:** WP Engine, Kinsta, Flywheel, SiteGround, InMotion Hosting, HostGator, GoDaddy, DreamHost, FastComet, Bitnami Apache, Bitnami Nginx, Google Cloud, AWS Lightsail, LiteSpeed Hosting, Ploi.io, CloudPanel, RunCloud, **Softaculous**, aaPanel, Rocket.net, and YunoHost.
* **Web servers:** Nginx, Apache, LiteSpeed, and IIS.
* **Plugins and builders:** WooCommerce, Elementor, Divi, Bricks Builder, Blocksy, Avada, Nicepage, Riode, Gutenberg Block Editor, WPML, Polylang, Weglot, WP Rocket, LiteSpeed Cache (QUIC.cloud), W3 Total Cache, WP Super Cache, WP Fastest Cache, SiteGround Optimizer, NitroPack, Autoptimize, Hummingbird, Cachify, Cache Enabler, CDN Enabler, Asset CleanUp, Debloat, Wordfence Security, Sucuri Security, Solid Security, Defender Security, WP Cerber, Shield Security, BBQ Firewall, Really Simple SSL, Yoast SEO, Rank Math, All in One SEO, Squirrly SEO, Contact Form 7, Gravity Forms, Jetpack, bbPress, MainWP, ManageWP, FluentSMTP, Limit Login Attempts Reloaded, Loginizer, and WP Social.

== Installation ==

= From your WordPress dashboard =

1. Open **Plugins > Add New** in your WordPress admin area.
2. Search for **"WP Ghost"**.
3. Click **Install Now**, then click **Activate**.
4. Open the **WP Ghost menu** in the left sidebar.
5. Enter your email address to activate your **free access token**.
6. Save the recovery file containing your **Safe URL** before changing login paths.
7. Select a preset on the **Level of Security** screen and run Frontend Check or **Ghost Doctor** to confirm your paths load.

= Manual installation =

1. Download `hide-my-wp.zip` from WordPress.org or your WP Ghost account.
2. Log in to `/wp-admin` as an Administrator.
3. Open **Plugins > Add New > Upload Plugin**.
4. Choose `hide-my-wp.zip` and click **Install Now**.
5. Click **Activate Plugin** and enter your email address to connect your free token.

= Reporting a security vulnerability =

**Report security issues in WP Ghost privately** through our Patchstack disclosure program at `patchstack.com/database/report` or email `security@hidemywpghost.com`. We acknowledge reports within 48 hours and coordinate disclosure within 90 days. **Please do not post vulnerability details in the public support forum before a fix ships.**

The full policy is in the plugin's `SECURITY.md` file, and bundled third-party components are listed in `sbom.json`.

**Support period:** We provide **security updates for 5 years** from each version's release date. Version 7.0.15, released October 26, 2026, is supported until October 2031.

= Documentation and guides =

* <a href="https://wpghost.com/kb/">WP Ghost Knowledge Base</a>
* <a href="https://wpghost.com/kb/9g-firewall/">9G Firewall guide</a>
* <a href="https://wpghost.com/kb/ghost-doctor/">Ghost Doctor self-check and repair guide</a>
* <a href="https://wpghost.com/kb/wp-ghost-paths-not-working-ghost-doctor/">Troubleshooting paths with Ghost Doctor</a>

== Screenshots ==

1. WP Ghost Overview: Choose your **Level of Security** to harden your site architecture.
2. Admin Security: Change and secure the wp-admin path and manage access to the default URL.
3. Paths Security: Customize and secure your login and registration entry points.
4. Core Security: Harden your system paths (wp-content, uploads, includes) against bot reconnaissance.
5. API & AJAX Security: Secure the REST API and admin-ajax paths and set access restrictions.
6. 9G Firewall Engine: Request filtering settings.
7. Brute Force Defense: Integrated Google reCAPTCHA and Math protection for authentication paths.
8. Modern Authentication: Secure logins with 2FA and Passkey (Passwordless) support.
9. Text Mapping: Change class names and IDs in your source code in page output.
10. URL Mapping: Rewrite internal URLs and asset paths.
11. Hardening Tweaks: Remove WordPress version tags and metadata.
12. Redirect Logic: Custom 404 and role-based redirect options for secured paths.
13. Safe Access: Manage **Temporary Logins** and Magic Links for developer access.
14. **Security Threats Log**: **Activate** the **Security Threats Log** to track blocked requests.
15. **Security Threats Log**: Recent threats blocked by WP Ghost.
16. Overview Dashboard: 7-day summary of security events.
17. Front-end View: Example of a custom login path (`/newlogin`).
18. Attack Blocked: Default `wp-login.php` returns a 404 error to bots.
19. Access Denied: Default `wp-admin` path is hidden from public requests.
20. Page source: Core WordPress paths rewritten in HTML output.

== Frequently Asked Questions ==

= What does WP Ghost do for WordPress security? =
WP Ghost provides request filtering, login protection and path controls, with Security Check tasks for WordPress hardening.

= Is WP Ghost a free WordPress security plugin? =
The free plugin includes the 9G Firewall, Brute Force protection, 2FA and Security Check; Premium adds features such as automated IP blocking and cloud event retention.

= Can I reduce repeated WordPress login attempts? =
WP Ghost lets you set login attempt limits and lockouts, add CAPTCHA, and require 2FA for selected user roles.

= Can I change wp-login.php without moving WordPress files? =
WP Ghost rewrites public URLs while leaving WordPress files in their existing directories.

= Is hiding the WordPress login page enough to secure my site? =
A custom login URL reduces exposure at the default address, but WordPress still needs updates, strong authentication and backups.

= Does WP Ghost remove malware from an infected site? =
WP Ghost focuses on prevention and configuration checks; use a malware scanner and a cleanup process for an existing infection.

= Do I still need WordPress security controls if my host has a firewall? =
Check which protections your host provides, then use WP Ghost for missing controls such as custom login paths or role-based 2FA.

= Can WP Ghost help with WooCommerce card testing? =
WP Ghost can limit repeated checkout submissions and failed payment attempts from the same IP address; distributed attacks may need controls from your payment provider or host.

= What does the free 9G Firewall check? =
From version 7.0.15, WP Ghost includes 9G checks for injection patterns, AJAX and REST requests, and malicious uploads, with comment and form checks on levels 3 and 4.

= How does Ghost Doctor fix broken paths? =
**Ghost Doctor** tests the homepage and asset URLs as a logged-out visitor, applies supported repairs, and reverts changes that fail its follow-up check.

= Will Ghost Doctor change my settings automatically? =
**Ghost Doctor** starts when you click **Start Diagnosis** under **WP Ghost > Level of Security** and saves a snapshot for **Undo All Changes**.

= What are AI Security Explanations? =
**AI Security Explanations** turn Security Check findings into instructions for your configuration and require an **active subscription** with a monthly check allowance.

= What data does AI Security Explanations send? =
The request contains Security Check findings and six setup fields: server type, config writability, rewrite presence, cache plugin presence, security mode and mapped file count.

= Does WP Ghost work without AI Security Explanations? =
WP Ghost's local Security Check, firewall, path controls and **Ghost Doctor** work without using **AI Security Explanations**.

= Can I use WP Ghost alongside Wordfence, Sucuri or Solid Security? =
WP Ghost supports use alongside these security plugins; review overlapping login, firewall and URL settings, then test your site's forms.

= Will changing WordPress paths affect SEO? =
WP Ghost path changes target system URLs rather than post permalinks, but blocked assets or crawler rules can affect search access. Run Frontend Check and verify public pages after changes.

= My theme CSS is broken after saving new paths. What should I do? =
Open **WP Ghost > Level of Security** and click **Start Diagnosis** so **Ghost Doctor** can test asset URLs and supported repairs.

= I forgot my custom login URL. How do I get back in? =
Use the **Safe URL** in your saved recovery file, or rename `/wp-content/plugins/hide-my-wp` through FTP/SFTP to deactivate WP Ghost and restore its default login route.

= Does WP Ghost support WordPress Multisite and Nginx? =
WP Ghost supports Multisite and generates Nginx rewrite rules, which may need your host to apply them.

= Can my host restrict custom WordPress paths? =
Managed hosts can restrict rewrite rules, so check your host's requirements before changing `wp-admin` or `wp-login.php`.

= How do I change paths inside the wp-admin dashboard too? =
Add `define('HMW_ALWAYS_CHANGE_PATHS', true);` to `wp-config.php` and save your WP Ghost settings after backing up the configuration file.

= Can I block AI crawlers and still appear in AI search? =
WP Ghost Premium includes crawler controls, but blocking search crawlers such as PerplexityBot can limit discovery in their services. Review the selected rules before enabling **AI Copyright Protection**.


== Changelog ==

= 7.0.15 (26 Oct 2026) =

* New - 9G Firewall: the new firewall generation with the latest 2026 threat protection, covering admin-ajax, the REST API, file uploads and more
* Update - The plugin verifies the SSL certificate on its own path and security check requests
* Update - Firewall checks comment and form submissions on levels 3 and 4
* Update - Password and payment fields stay private in the threat log
* Update - Country database saved in the uploads folder and downloaded in the background
* Update - Security Check warns about PHP2Shell (CVE-2026-109618)
* Update - Translated the new texts in all bundled languages

= 7.0.14 (14 Oct 2026) =

* Update - Security update: Hide Source Map References now removes source map comments only inside inline scripts and styles, so text in a visitor comment can no longer turn a link into JavaScript (CVE-2026-89051).
* Fix - Firewall level 2 no longer blocks visitors from Facebook, Instagram and Google Ads when the fbclid or gclid token contains x1b, x20 or null.
* Fix - Firewall level 4 checks its short malware-name list on the URL path only, so random fbclid and gclid tokens no longer match it.
* Fix - Whitelisting a firewall rule now skips only that rule, and the other rules still check the request.
* Fix - The two-factor and Magic Login screens no longer stop with a PHP error on WordPress versions before 6.4.
* Update - Translated the new texts in all bundled languages: Arabic, Chinese, Dutch, Finnish, French, German, Indonesian, Italian, Japanese, Portuguese, Romanian, Russian, Spanish and Turkish.

= 7.0.13 (3 Oct 2026) =

* New - Weekly email summary of blocked threats, logins and blocked IPs. Turn it on or off in Advanced, Notifications.
* New - Check your reCAPTCHA keys on the Brute Force page without logging out.
* New - WooCommerce checkout protection limits repeated checkout attempts and failed payments from the same IP, to stop card testing.
* New - Email alert when the activity log or the threats log is turned off.
* Update - reCAPTCHA v2 keeps the login button disabled until the box is checked.
* Update - Right-click, copy and paste protection now skips form fields, so visitors can fill and paste in forms.
* Update - Firewall and brute force now run on requests with an unverified login cookie, so a forged cookie cannot skip them.
* Update - 6G firewall now also checks the query string on browser requests.
* Update - The login math question uses a one time token, so an answer cannot be reused.
* Fix - Temporary Login and Magic Login links expire correctly on PHP 8.
* Fix - A corrupted firewall whitelist no longer causes a fatal error.

= 7.0.12 (29 Sept 2026) =

* New - Random Static Number now has a Random Number Type option: a random version for each file that changes when the file version changes
* Fix - reCAPTCHA V3 and Enterprise now add their token to login forms built with page builders like Elementor
* Fix - reCAPTCHA V3 and Enterprise no longer change or resubmit other forms on the same page
* Fix - With WooCommerce Support turned off, the WooCommerce login is no longer refused with "Invalid ReCaptcha"
* Fix - CSS and JS files served by the plugin now send a valid Content-Type header
* Fix - Random Static Number no longer saves a new number in the database on every page view of a logged in user
* Fix - Hide Source Map no longer cuts off the page when a cache plugin like LiteSpeed Cache minifies the HTML

= 7.0.11 (10 September 2026) =

* Update - Security update: the WooCommerce AJAX and API exceptions now confirm the request names a real WooCommerce action and arrives on the WooCommerce endpoint, and no longer reveal a hidden URL
* Fix - The custom REST API path is now used during scheduled tasks, so plugins that renew a connection in the background, like FluentSMTP with Microsoft 365, no longer fail

= 7.0.10 (2 September 2026) =

* Update - Security update: the compatibility exceptions for WooCommerce, WPML and MainWP now confirm the request really comes from those plugins before the plugin steps aside
* Update - Security update: compatibility paths are matched from the site root, so an address that only contains a known path no longer matches
* Update - Security update: the iThemes/Solid Security loopback exception now requires that plugin to be active and a matching verification value
* Update - Security update: Temporary Login and Magic Login make the login page reachable only once the token matches a real user
* Update - Security update: the page builder preview exception now requires editing rights, and the logged in check during early load is stricter
* New - One click login support for the hosting panels: Softaculous (cPanel, CentOS Web Panel, DirectAdmin, Webuzo, Plesk) and aaPanel WP Toolkit
* Update - Security update: hardening for the way the plugin resolves and handles the current request
* Fix - CSS and JS are compressed only with what the browser actually accepts, instead of always answering compressed
* Fix - Compressed responses now use gzip, which every browser and CDN reads the same way
* Fix - Static files and the REST API path are now handled correctly when your site is reached on an alias domain
* Fix - REST API responses handled by the plugin no longer carry compression headers that do not match the returned content
* Fix - LiteSpeed: the QUIC.cloud address list is refreshed on its own and no longer kept forever, so QUIC.cloud keeps working as their addresses change
* Fix - LiteSpeed: the QUIC.cloud addresses are loaded even when the plugin settings have not been saved since LiteSpeed was installed
* Fix - Defender Security: the free edition of the plugin is now recognized, not only the Pro edition
* Fix - Ghost Doctor no longer reports the CSS and JS files as not loading when Text Mapping in CSS and JS files is on and WordPress serves those files on purpose
* Fix - Ghost Doctor now checks the REST API path the way WordPress answers it, instead of reporting a working REST API as missing
* Fix - Changing the REST API path takes effect on save, without having to open Settings > Permalinks and save again
* Fix - No longer stops with a fatal error on servers where the getallheaders() function is missing
* Fix - Frontend Check no longer shows the word "undefined" at the end of the results when a path or an asset fails
* New - New hmwp_files_allowed_hosts, hmwp_files_request_headers, hmwp_files_response_headers, hmwp_files_skip_headers, hmwp_files_cache, hmwp_files_cache_dir and hmwp_files_cache_lifetime filters for developers

= 7.0.09 (17 August 2026) =

* New - AI Security Explanations: every finding on the Security Check page explained for your own website, in plain language
* New - AI Security Explanations rank your findings by what actually exposes your site, using your server type, your rules and your security mode
* New - A plain-language summary of your whole website, above the list of what to fix
* New - Open Details on any finding to read why it matters on your server and what an attacker would do with it
* Fix - Fix it now works for the Security Check tasks that change a plugin setting, such as Hide Old Paths, Hide Common Files, Hide wp-login and Disable XML-RPC
* Fix - Fix it no longer answers with "Ajax is not loading correctly. Clear all cache and try again." after the paths have been changed
* Fix - The Security Check summary now shows whether your paths are working instead of always reporting that they have not been checked
* Update - Published a coordinated vulnerability disclosure policy (SECURITY.md), a list of the third-party components we bundle (sbom.json), and a declared 5 year security update period

= 7.0.08 (27 July 2026) =

* New - Ghost Doctor: finds what is breaking your paths and repairs it in one click
* New - Ghost Doctor can switch to protection that needs no server rules when your server will not serve them
* New - Security Check now opens with one prioritized list of what to fix first
* New - Change Paths shows one card after a path change instead of three
* Fix - Security Check no longer reports wp-content, the login path or the admin path as visible on sites that are hiding them correctly
* Fix - Frontend check no longer reports a failure when Disable REST API Access is on
* Fix - Open on a failing path check no longer leads to a 404 while the new paths are not working yet
* Fix - Security Tasks no longer shows an empty table when every task passed
* Fix - Last check now shows how long ago the scan ran, not a clock time

= 7.0.07 (20 July 2026) =

* Security - Two-Factor Authentication settings are now bound to the account they belong to, so the 2FA method, authenticator, email codes, backup codes and passkeys can only be managed by the account owner or by an administrator allowed to edit that user
* Security - Passkey enrollment is now always self-service, corresponding to the device the passkey is created on
* Security - Temporary Login updates and deletions now apply only to temporary accounts, so regular accounts are never affected from this screen
* Security - The role assigned to a temporary login is now validated and can never grant more capabilities than the user creating it already has
* Security - Magic Login links now require the same permissions as the screen they are offered from

= 7.0.06 (15 July 2026) =

* Fix - Editing a custom post type that uses the built-in Categories/Tags taxonomy no longer crashes the Block Editor
* Fix - Block Editor no longer crashes with "Cannot read properties of undefined" when Hide User Enumeration is on
* Fix - Settings page navigation menu no longer loses its layout on sites where another plugin or theme restricts the allowed HTML tags

= 7.0.05 (08 July 2026) =

* New - Force 2FA Setup: require selected user roles to set up Two-Factor Authentication before they can access the dashboard, with a guided setup screen shown right after login
* Fix - Temporary Login page no longer triggers a fatal error when opened for a user that was deleted or expired
* Fix - Two-Factor email setup and Magic Login no longer emit PHP warnings when the requested user no longer exists
* Fix - WP-CLI commands no longer emit a PHP 8.5 "Undefined array key hostname" warning
* Fix - Settings page navigation menu no longer loses its layout on sites where another plugin or theme restricts the allowed HTML tags; the menu now keeps its own classes and attributes regardless of the site's wp_kses filters

= 7.0.04 (29 June 2026) =

* New - Frontend Check now also verifies the theme's CSS and JS files load, catching a broken layout on pages that still return 200
* New - Detects when CSS/JS/font files load through WordPress instead of the server config, falls back to safe paths and warns you on the settings page
* Update - Redesigned the Frontend Check results into clear, uniform rows that stay readable with long URLs

= 7.0.03 (02 June 2026) =

* Fix - WPML/Polylang with a custom or renamed REST API path: WPML Advanced Translation Editor and other REST API calls no longer fail with a network error in the admin
* Fix - REST API requests made through the ?rest_route= form are no longer mistakenly treated as normal page requests and blocked
* Fix - Renamed REST API path: legacy, cached and external clients still calling the default wp-json path are now recognized correctly instead of being 404'd
* Fix - Compatibility module for WPML: the Advanced Translation Editor sync routes and WPML/ICL admin-ajax requests are no longer rewritten with the active language prefix
* Fix - Some sites could show a blank page when source code optimization was enabled; the header/tag find & replace is now fail-safe and never blanks the output on a regex error
* Fix - Prefetch/speculation rules cleanup now removes the wp-admin and wp-*.php entries without leaving the rules JSON invalid
* Security - Hardened REST API detection: the firewall can no longer be bypassed by appending /wp-json/ (or ?rest_route=) to the query string of another request
* Security - Brute force protection also covers REST API Application Password authentication
* Security - Fixed unauthenticated Open Redirect via the redirect_to parameter on the custom logout URL.

= 7.0.02 (11 May 2026) =

* Fix - Compatibility with WPML and Polylang: static asset URLs (wp-content, wp-includes) no longer get the language prefix prepended (e.g. /en/wp-content/...) when "Change Relative URLs to Absolute URLs" is enabled
* Fix - Password-protected pages (built-in WordPress post password) now submit correctly on Nginx and other servers without server-level rewrites when the login URL is customized
* Fix - Refreshed knowledge base links across admin notices to point to the new documentation
* Fix - Minor bugs and typos

= 7.0.01 (15 April 2026) =

* Update - Added the option to roll back to the last stable version in the Backup/Restore page
* Fix - Corrected the colors in the dark mode style
* Fix - Rewrite rules not correctly displayed in the notification bar when rules must be added manually to the server config
* Fix - Typos and minor bugs

= 7.0.00 (31 March 2026) =
Major Release: Security Score, Login Page Designer, Passkey 2FA, Security Threats Log, User Events Log, GEO Threats Map, and expanded 7G/8G Firewall rules. Free update for all users.

* New - Security Optimization Score (0-100) with dynamic gauge on the Overview dashboard and Security Check page
* New - Security Threats Log to track blocked attacks and malicious requests (limited view)
* New - User Events Log to track login activity and user changes (limited view)
* New - GEO Threats Map with top 5 threat countries on the Overview dashboard
* New - Country filter in Security Threats Log and User Events Log
* New - Login Page Designer with custom logo, background image, colors, and color scheme presets
* New - Security Presets for one-click activation: Minimal, Lite Mode + Firewall + Compatibility, and Lite Mode + Firewall + Brute Force + Logs + Two Factor
* New - Contextual upgrade suggestions based on your site's actual threat data
* New - Two-Factor Authentication by Passkey (Face ID, Touch ID, Windows Hello)
* New - Each user can pick their preferred 2FA method from their profile
* New - Trust current browser option for 2FA, skip verification on trusted devices
* New - Hide WordPress Common Files (wp-config.php, readme.html, license.txt, php.ini)
* New - Hide WordPress Common Paths with file extension filtering (html, txt, lock)
* New - Hide User Enumeration to block author discovery scans
* New - Dark mode support (browser-based)
* New - Translation in Indonesian (id_ID) language
* New - Translation in Turkish (tr_TR) language

Firewall & Security Updates:

* Update - Expanded 7G & 8G Firewall rules to block SQL injection, XSS, file inclusion, directory traversal, and automated vulnerability scans
* Update - Firewall rules optimized for lowered overhead under high attack traffic
* Update - Improved threat detection to stop malicious requests before WordPress core execution
* Update - Security Headers updated with latest best practices
* Update - Notification on Overview to activate 7G/8G Firewall when unblocked threats are detected

Compatibility:

* Update - Compatibility with WordPress 7.0 and PHP 8.5
* Update - Compatibility with WooCommerce 10.6
* Update - Compatibility with Elementor, Bricks Builder, Blocksy, Nicepage, Avada, and Riode themes
* Update - Compatibility with WP Rocket, LiteSpeed, Nitropack, SiteGround Optimizer, and Cloudflare
* Update - Compatibility with Wordfence, Sucuri, and Solid Security
* Update - Compatibility with WP Engine, Kinsta, CloudPanel, Ploi.io, and IIS servers
* Update - Compatibility with WPML and Polylang for multilingual Brute Force, 2FA, and Magic Login texts
* Update - Translations updated in all 14 supported languages

UI & Experience:

* Update - Redesigned Overview dashboard with Security Optimization Score, threat chart, and GEO map
* Update - Improved Security Check page with numeric score and actionable task list
* Update - Reorganized Logs section into Security Threats and User Events
* Update - Improved activation flow with more clear setup wizard
* Update - Translations updated in all 16 supported languages: Arabic, Brazilian Portuguese, Chinese (Simplified), Dutch, Finnish, French, German, Indonesian, Italian, Japanese, Portuguese, Romanian, Russian, Spanish, Turkish, and English (default).
* Fix - Dropdown and Help icon in the RTL languages

= 5.5.04 (26 Mar 2026) =

* Update - Firewall rules on WP core init
* Update - Added compatibility with Photo Gallery from 10Web
* Fix - Security Check to update the options and handle properly
* Fix - Small bugs

= 5.5.02 (10 Feb 2026) =

* Fix - Compatibility with IIS Server
* Fix - Compatibility with LiteSpeed Quic Cloud

= 5.5.01 (22 Dec 2025) =

* Update - Added the option to hide WordPress Common Paths with extension html, txt, lock
* Update - Added the option to hide WordPress Common Files like wp-config, readme.html, license.html, php.ini
* Update - Added the option to Hide Source Map References
* Update - Added the option to Hide User Enumeration

= 5.4.08 (09 Dec 2025) =

* Update - Compatibility with WP 6.9
* Fix - Remove the wp-*.php and admin path from prefetch paths in WP 6.9
* Update - 2FA to allow each user to select the 2FA method in the profile
* Update - 2FA to connect through passkey and fingerprint
* Update - 2FA to trust the current browser

= 5.4.07 (29 Sept 2025) =

* Update - Compatibility with the plugin WP Social & WP Social PRO
* Update - Compatibility with LiteSpeed Quic Cloud on IPV6
* Update - Make REST API test work when permalinks are set to the default PHP parameter
* Update - Minimum PHP version required is 8.0 in the Security Check section
* Update - Added New Feature Two-factor Authentication By Passkey (2FA)
* Update - Whitelist more known AI Chatbots in firewall rules

= 5.4.06 (21 Aug 2025) =

* Update - Firewall rules for more compatibility
* Update - Safe URL verification process
* Update - Compatibility with the plugin Debloat

= 5.4.05 (27 May 2025) =

* Update - Add AI support in the plugin settings
* Update - 7G & 8G Firewall for more compatibility with WP Plugins
* Update - Compatibility with Riode theme for Brute Force
* Fix - Compatibility WooCommerce login/register with reCaptcha V3
* Fix - Update check error

= 5.4.04 (21 Mar 2025) =

* Update - Compatibility with the WP 6.8
* Fixed - Function _load_textdomain_just_in_time was called incorrectly

= 5.4.03 (11 Mar 2025) =

* Update - Compatibility with the new WP Engine rewrite rules
* Update - Add the option to customize all active and inactive themes
* Fix - File security when the rewrite rules are not loaded correctly
* Fix - Prevent Brute Force from updating the warning text lacking space when switched off
* Fix - Prevent PHP warning when IP address unknown in Brute Force IP check
* Fix - Load i18n on login page for password-strength-meter messages when the Clean Login option is activated
* Fix - File security when the rewrite rules are not loaded correctly
* Fix - Dynamic file mapping to load through index.php for better compatibility with all server types

= 5.4.02 (04 Mar 2025) =

* Update - Security update on wp-activate.php path call
* Update - Translations in all languages for the last changes
* Update - the Brute Force to load Google Enterprise reCaptcha
* Update - Brute Force compatibility with other plugins
* Fix - Headers check regarding Brute Force to get the real IP behind Proxy
* Fix - Include parent theme in the custom theme name list if the child theme is loaded
* Fix - Admin layout issue when other plugins notification is loading in Wp Ghost settings
* Fix - Prevent redirecting URLs to concealed paths like new admin path or new login path
* Fix - Paths changed in cache files when CSS and JS files are loaded dynamically
* Fix - Hide the new login on registration redirect when the registration is deactivated
* Fix - Remove newlines from the rewrite rules
