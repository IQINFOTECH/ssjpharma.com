-- Phase 6: index products.updated_at — the admin product list sorts by it
-- (ORDER BY p.updated_at DESC), which otherwise filesorts. Non-destructive.
ALTER TABLE `products` ADD KEY `idx_products_updated` (`updated_at`);
