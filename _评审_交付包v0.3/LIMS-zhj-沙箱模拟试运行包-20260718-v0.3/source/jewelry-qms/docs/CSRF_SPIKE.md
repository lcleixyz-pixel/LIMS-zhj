# CSRF Spike Notes

## Verified ThinkPHP APIs

- Token generation helper: `token()` returns the raw token value.
- Meta helper: `token_meta()` renders `<meta name="csrf-token" content="...">`.
- Hidden input helper: `token_field()` renders a `__token__` hidden field.
- Route middleware: `think\middleware\FormTokenCheck`.
- Route shortcut: `Route::rule(...)->token()` attaches the same middleware.
- Accepted request carriers:
  - POST field `__token__`
  - Header `X-CSRF-TOKEN`

## Runtime Behavior

`think\Request::checkToken()` returns true for `GET`, `HEAD`, and `OPTIONS`.
For mutating requests it verifies the session token, then deletes it after a
successful check. This means tokens are single-use within a rendered page.
Failed checks also reset the stored token, so smoke tests must fetch a fresh GET
page before retrying a valid POST.

## Applied Scope

This pass protects the authenticated QMS route group by adding
`FormTokenCheck` after authentication and authorization middleware. The main
layout renders a token meta tag, and `public/static/js/csrf.js` injects that
token into every `method="post"` form and future jQuery AJAX requests.

Login remains outside the authenticated group and should be handled separately
if a stricter public-form CSRF policy is required.
