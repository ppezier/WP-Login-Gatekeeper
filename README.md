# WP Login Gatekeeper

> Bring Cloudflare Turnstile protection to all native WordPress forms — login, registration, lost password, and password reset — with zero configuration beyond your API keys.

## Features

- Injects the Cloudflare Turnstile widget into all four native `wp-login.php` forms
- Server-side token verification on every form submission
- Sends the visitor's IP address to the Turnstile API for improved accuracy
- No extra JavaScript to manage: the Cloudflare script is loaded automatically with the widget
- Uses Turnstile's **Managed** mode by default — invisible or challenge, decided automatically based on detected risk
- Translatable via standard WordPress i18n functions (`__()`, `esc_html__()`)
- Follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)

## Protected forms

| Form | WordPress hook (widget) | WordPress hook (validation) |
|---|---|---|
| Login | `login_form` | `authenticate` filter |
| Registration | `register_form` | `registration_errors` filter |
| Lost password | `lostpassword_form` | `lostpassword_post` action |
| Password reset | `resetpass_form` | `validate_password_reset` action |

## Installation

### Manual (recommended for testing)

1. Download or clone this repository into your `wp-content/plugins/` directory:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/ppezier/WP-Login-Gatekeeper.git wp-login-gatekeeper
   ```
2. In the WordPress admin, go to **Plugins → Installed Plugins** and activate **WP Login Gatekeeper**.
3. Enter your API keys under **Settings → WP Login Gatekeeper** (see [Configuration](#configuration)).

### Via WordPress admin

1. Download the repository as a ZIP file (`Code → Download ZIP` on GitHub).
2. In the WordPress admin, go to **Plugins → Add New Plugin → Upload Plugin**.
3. Select the ZIP file and click **Install Now**, then **Activate Plugin**.
4. Enter your API keys under **Settings → WP Login Gatekeeper** (see [Configuration](#configuration)).

## Configuration

Once the plugin is activated, go to **Settings → WP Login Gatekeeper** in the WordPress admin.

Get your keys from the [Cloudflare Turnstile dashboard](https://dash.cloudflare.com/?to=/:account/turnstile):

1. Log in to the Cloudflare dashboard.
2. Go to **Turnstile** in the left sidebar.
3. Click **Add site**, fill in your domain, and choose **Managed** widget type.
4. Copy the **Site Key** and **Secret Key** into the corresponding fields and click **Save Changes**.

> The keys are stored in `wp_options` via the WordPress Settings API — no file editing required.

## How It Works

```
Browser                         WordPress                      Cloudflare API
  │                                 │                               │
  │── submits form ────────────────►│                               │
  │   (includes cf-turnstile-       │── POST /siteverify ──────────►│
  │    response token)              │   secret + token + IP         │
  │                                 │◄── { "success": true } ───────│
  │◄── allowed / error ─────────────│                               │
```

1. The Turnstile widget renders in the browser and silently (or visibly) challenges the visitor.
2. On form submission, the browser sends a `cf-turnstile-response` token along with the form data.
3. WordPress intercepts the submission via the appropriate hook, extracts and sanitizes the token, then calls the Cloudflare siteverify API.
4. If verification fails or the token is missing, the submission is rejected with a standard WordPress error message.
