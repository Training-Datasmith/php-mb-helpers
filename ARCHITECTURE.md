# Architecture: php-mb-helpers

## Purpose

A polyfill library providing multibyte-safe string helper functions that are missing from PHP's built-in `mbstring` extension. Each function is only defined if it does not already exist in the current PHP runtime.

## Directory Structure

```
src/
  mb_helpers.php   — Polyfill function definitions (mb_ucwords, mb_ucfirst, mb_strrev, mb_str_pad, mb_count_chars, mb_str_split)
```

## Key Design Decisions

- **Guard-wrapped definitions**: Every function is wrapped in `if (!function_exists(...))` so the file is safe to include on PHP versions that natively provide some or all of these functions
- **Encoding parameter**: Each function accepts an `$encoding` parameter (defaulting to `UTF-8`) to match the signature pattern of PHP's built-in `mb_*` functions
- **No classes**: Pure procedural polyfills — this matches the style of the functions being shimmed

## Extension Points

- Call any of the provided functions exactly as you would their native equivalents
- Pass a custom `$encoding` to work with non-UTF-8 strings

## Functions Provided

| Function | Description |
|---|---|
| `mb_ucwords` | Capitalize the first letter of each word |
| `mb_ucfirst` | Capitalize the first character of a string |
| `mb_strrev` | Reverse a multibyte string |
| `mb_str_pad` | Pad a multibyte string to a given length |
| `mb_count_chars` | Count character occurrences (modes 1 and 3) |
| `mb_str_split` | Split a multibyte string into an array of characters |
