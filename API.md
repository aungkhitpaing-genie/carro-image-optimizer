# Carro Image Optimizer API

HTTP API for generating WordPress-style image variants from a source image on
a configured storage disk or a remote CDN URL.

A typed PHP / Laravel client for this API is available in [`sdk/`](sdk/README.md).

## Base URL

All endpoints are served under the `/api` prefix, e.g.
`https://images.carro.test/api`.

## Authentication

Every endpoint requires an API key sent in the `X-Api-Key` header. The key must
match the `API_KEY` configured on the service.

```
X-Api-Key: your-api-key
```

A missing or invalid key returns `401 Unauthorized`:

```json
{ "message": "Unauthenticated." }
```

---

## `GET /api/status`

Health check for the service.

### Response — `200 OK`

```json
{
    "status": "ok",
    "service": "Image Optimizer"
}
```

`service` reflects the configured application name.

---

## `POST /api/variants/generate`

Generate one or more image variants from a source image. Existing variants are
reused unless `overwrite` is `true`. Generated variants are written next to the
source under the configured `variant_dir` (default `variants`).

### Request body

| Field        | Type     | Required | Description                                                                                          |
| ------------ | -------- | -------- | ---------------------------------------------------------------------------------------------------- |
| `disk`       | string   | yes      | A configured filesystem disk. For a remote `path`, this is where variants are written.               |
| `path`       | string   | yes      | A disk-relative key (e.g. `uploads/photo.jpg`) **or** an `http(s)` CDN URL.                           |
| `mime_type`  | string   | no       | Source MIME type; must start with `image/`. Guessed from the path/extension when omitted.            |
| `file_name`  | string   | no       | Original file name, used to resolve the extension when `path` has none. Defaults to the path's name. |
| `variants`   | string[] | no       | Variant keys to generate. Omit to generate every configured preset.                                  |
| `overwrite`  | boolean  | no       | Regenerate variants that already exist. Defaults to `false`.                                         |

#### Variant keys

The configured presets are:

| Key            | Width  | Height | Fit  |
| -------------- | ------ | ------ | ---- |
| `thumbnail`    | 150    | 150    | crop |
| `medium`       | 300    | 300    | max  |
| `medium_large` | 768    | —      | max  |
| `large`        | 1024   | 1024   | max  |
| `x_large`      | 1536   | 1536   | max  |
| `xx_large`     | 2048   | 2048   | max  |

`max` fits the image inside the box without upscaling; `crop` keeps exact
dimensions.

#### Remote (CDN) sources

When `path` is an `http(s)` URL the service fetches the original over HTTP and
writes the variants to `disk` under the key derived from the URL path (host and
query string are discarded). For example, `https://cdn.example.com/uploads/photo.jpg?v=2`
maps to the key `uploads/photo.jpg`, producing `uploads/variants/photo-thumbnail.jpg`.

### Example request

```bash
curl -X POST https://images.carro.test/api/variants/generate \
  -H "X-Api-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "disk": "s3",
    "path": "uploads/photo.jpg",
    "variants": ["thumbnail", "medium"],
    "overwrite": false
  }'
```

### Response — `200 OK`

```json
{
    "data": {
        "disk": "s3",
        "source_path": "uploads/photo.jpg",
        "variants": {
            "thumbnail": "uploads/variants/photo-thumbnail.jpg",
            "medium": "uploads/variants/photo-medium.jpg"
        },
        "generated_count": 2
    }
}
```

`variants` maps each requested key to its stored path on `disk`. Keys that
already existed (and were reused) are still included.

### Errors

| Status | When                                                              | Body                                                                                 |
| ------ | ----------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `401`  | Missing or invalid API key                                        | `{ "message": "Unauthenticated." }`                                                  |
| `404`  | Source image not found on the disk or remote URL                  | `{ "message": "Source image not found on disk [s3]: uploads/photo.jpg" }`            |
| `422`  | Validation failed                                                 | `{ "message": "...", "errors": { "disk": ["The selected disk is not configured."] } }` |
| `422`  | Source is not a supported image (MIME type)                       | `{ "message": "Unsupported or missing image mime type: application/octet-stream" }`  |
| `422`  | Source extension is not supported (`jpg`, `png`, `webp`, `gif`)   | `{ "message": "Unsupported image extension: tiff" }`                                  |
| `422`  | No variants could be generated (e.g. corrupt source)             | `{ "message": "No variants were generated." }`                                       |

#### Validation rules

- `disk` — required; must be a configured filesystem disk.
- `path` — required string.
- `mime_type` — optional; must start with `image/`.
- `variants.*` — must be one of the configured variant keys above.
- `overwrite` — optional boolean.

A validation failure returns `422` with an `errors` object keyed by field name.
