# Product image uploads

New Admin gallery uploads use `ProductImageUpload`. The exact uploaded bytes are retained on the private `local` disk at `product-originals/<uuid>.source`. The corresponding public path stored in the existing `image_path` field is `products/branded/<uuid>.webp`. No schema change is needed. Originals have no public link; recovery uses authorized server/storage access and the UUID, with the actual format identified from the file bytes.

JPEG/PNG/WebP only, 5 MB and 12 megapixels maximum. The public copy is oriented, reduced to at most 2000 pixels on its longest edge, encoded as WebP quality 90, and marked with the existing Novelion emblem at 48% opacity, 12% of the shorter edge, bottom right. Transparency is retained. The Admin client-side editor is deliberately disabled so the original arriving at the server is not already cropped/re-encoded. Unsupported/corrupt uploads fail without replacing the current image.

Existing images are unchanged. Replacement does not delete previous files; removing an image record keeps the private original for recovery. Existing product deletion still removes associated public copies. Private originals require an explicit retention decision before any future cleanup; no automatic purge is introduced.

## Optional existing-image branding (not executed)

Back up the image records and public files first. For each explicitly approved image, feed a copy of the existing public file to `ProductImageUpload::store`, verify the derivative, then update only that image record's `image_path`. Keep the old public file and record the old/new mapping for rollback. The private original is recoverable by the derivative UUID. Do not perform a bulk rewrite or delete legacy URLs without separate authorization.

Deployment requires GD with WebP support. No dependency installation or migration is required. Build frontend assets locally and synchronize only the generated Vite assets/manifest to the production document root; preserve `.htaccess` and `storage`.

Gallery images disable dragging only. Context menus, selection and keyboard controls remain available. Watermarks provide branding and casual deterrence, not DRM.
