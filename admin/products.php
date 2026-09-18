<?php
// admin/products.php
require_once dirname(__DIR__) . '/config/admin_init.php';
require_once dirname(__DIR__) . '/includes/admin-upload-helper.php';
require_once dirname(__DIR__) . '/includes/media-helper.php';
require_once dirname(__DIR__) . '/includes/product-helper.php';

// Run idempotent database schema check once for Enterprise Landing Page CMS columns
run_product_cms_migrations($pdo);

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$id = 0;
if (isset($_GET['id'])) {
    $parsedId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($parsedId === false || $parsedId <= 0) {
        set_flash('error', __('invalid_product_id'));
        header('Location: products.php');
        exit;
    }
    $id = $parsedId;
}
$error_message = '';
$success_message = '';

// Handle POST actions for product flag toggles, sort order updates, and deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $postAction = $_POST['action'];

    if ($postAction === 'toggle_flag' || $postAction === 'update_sort_order' || $postAction === 'delete_product') {
        // Validate CSRF token
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            set_flash('error', __('csrf_invalid'));
            header('Location: products.php');
            exit;
        }

        // Validate product ID as integer
        $productId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($productId === false || $productId <= 0) {
            set_flash('error', __('invalid_product_id'));
            header('Location: products.php');
            exit;
        }

        // Allowlist for boolean flag fields
        $allowedFlags = ['is_featured', 'is_most_requested', 'is_new', 'is_active'];

        if ($postAction === 'toggle_flag' && isset($_POST['flag'])) {
            $field = $_POST['flag'];
            // Validate flag name against allowlist
            if (!in_array($field, $allowedFlags, true)) {
                set_flash('error', __('invalid_flag'));
                header('Location: products.php');
                exit;
            }
            try {
                // Read current value, then toggle
                $stmt = $pdo->prepare("SELECT $field FROM products WHERE id = :id");
                $stmt->execute([':id' => $productId]);
                $current = $stmt->fetchColumn();
                if ($current === false) {
                    throw new ProjectValidationException(__('product_not_found'));
                }
                $newValue = ((int)$current) ? 0 : 1;

                $stmt = $pdo->prepare("UPDATE products SET $field = :value, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $stmt->execute([':value' => $newValue, ':id' => $productId]);
                
                set_flash('success', __('visibility_updated_success'));
            } catch (ProjectValidationException $e) {
                set_flash('error', $e->getMessage());
            } catch (PDOException $e) {
                error_log("Database error in products toggle flag: " . $e->getMessage());
                set_flash('error', __('database_operation_failed'));
            } catch (Throwable $e) {
                error_log("Unexpected error in products toggle flag: " . $e->getMessage());
                set_flash('error', __('unexpected_operation_failed'));
            }
            header('Location: products.php');
            exit;

        } elseif ($postAction === 'update_sort_order') {
            // Validate sort_order >= 0
            $sortValue = filter_var($_POST['sort_order'] ?? null, FILTER_VALIDATE_INT);
            if ($sortValue === false || $sortValue < 0) {
                set_flash('error', __('invalid_sort_order'));
                header('Location: products.php');
                exit;
            }
            try {
                // Confirm existence first
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE id = :id");
                $stmtCheck->execute([':id' => $productId]);
                if ((int)$stmtCheck->fetchColumn() !== 1) {
                    throw new ProjectValidationException(__('product_not_found'));
                }

                $stmt = $pdo->prepare("UPDATE products SET sort_order = :value, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $stmt->execute([':value' => $sortValue, ':id' => $productId]);
                
                set_flash('success', __('sort_order_updated'));
            } catch (ProjectValidationException $e) {
                set_flash('error', $e->getMessage());
            } catch (PDOException $e) {
                error_log("Database error in products sort order: " . $e->getMessage());
                set_flash('error', __('database_operation_failed'));
            } catch (Throwable $e) {
                error_log("Unexpected error in products sort order: " . $e->getMessage());
                set_flash('error', __('unexpected_operation_failed'));
            }
            header('Location: products.php');
            exit;

        } elseif ($postAction === 'delete_product') {
            try {
                // Confirm product exists and fetch image path
                $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = :id");
                $stmt->execute([':id' => $productId]);
                $image_to_delete = $stmt->fetchColumn();

                if ($image_to_delete === false) {
                    throw new ProjectValidationException(__('product_not_found'));
                }

                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
                $stmt->execute([':id' => $productId]);

                if ($stmt->rowCount() !== 1) {
                    throw new PDOException("Delete failed: row count was not 1");
                }
                $pdo->commit();

                $cleanupSuccess = true;
                if ($image_to_delete) {
                    $cleanupSuccess = safe_delete_file($image_to_delete, 'products');
                }

                if (!$cleanupSuccess) {
                    set_flash('success', __('product_deleted') . ' ' . __('cleanup_incomplete_warning'));
                } else {
                    set_flash('success', __('product_deleted'));
                }
            } catch (ProjectValidationException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                set_flash('error', $e->getMessage());
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Database error deleting product " . $productId . ": " . $e->getMessage());
                set_flash('error', __('database_operation_failed'));
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Unexpected error deleting product " . $productId . ": " . $e->getMessage());
                set_flash('error', __('unexpected_operation_failed'));
            }
            header('Location: products.php');
            exit;
        }
    }
}

// Handle Add/Edit Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = __('csrf_invalid');
    } else {
        $existing_product = null;
        if ($id > 0) {
            $stmt_existing = $pdo->prepare("SELECT * FROM products WHERE id = :id");
            $stmt_existing->execute([':id' => $id]);
            $existing_product = $stmt_existing->fetch();
        }

        $name_ar = isset($_POST['name_ar']) ? trim($_POST['name_ar']) : ($existing_product ? $existing_product['name_ar'] : '');
        $name_en = isset($_POST['name_en']) ? trim($_POST['name_en']) : ($existing_product ? $existing_product['name_en'] : '');
        $desc_ar = isset($_POST['description_ar']) ? trim($_POST['description_ar']) : ($existing_product ? $existing_product['description_ar'] : '');
        $desc_en = isset($_POST['description_en']) ? trim($_POST['description_en']) : ($existing_product ? $existing_product['description_en'] : '');
        $price = 0.00; // Price set to 0.00 (quote system)
        $stock = isset($_POST['stock']) ? intval($_POST['stock']) : ($existing_product ? (int)$existing_product['stock'] : 0);
        $category = isset($_POST['category']) ? trim($_POST['category']) : ($existing_product ? $existing_product['category'] : '');

        if (isset($_POST['dimensions_ar'])) {
            $dimensions_ar = trim($_POST['dimensions_ar']);
        } elseif (isset($_POST['dimensions'])) {
            $dimensions_ar = trim($_POST['dimensions']);
        } else {
            $dimensions_ar = $existing_product ? $existing_product['dimensions_ar'] : '';
        }

        if (isset($_POST['dimensions_en'])) {
            $dimensions_en = trim($_POST['dimensions_en']);
        } else {
            $dimensions_en = $existing_product ? $existing_product['dimensions_en'] : '';
        }
        $dimensions = $dimensions_ar;

        $materials_ar = isset($_POST['materials_ar']) ? trim($_POST['materials_ar']) : ($existing_product ? $existing_product['materials_ar'] : '');
        $materials_en = isset($_POST['materials_en']) ? trim($_POST['materials_en']) : ($existing_product ? $existing_product['materials_en'] : '');
        
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_most_requested = isset($_POST['is_most_requested']) ? 1 : 0;
        $is_new = isset($_POST['is_new']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : ($existing_product ? (int)$existing_product['sort_order'] : 0);

        // SEO & Rich Results fields
        $seo_title_ar = isset($_POST['seo_title_ar']) ? trim($_POST['seo_title_ar']) : ($existing_product ? $existing_product['seo_title_ar'] : '');
        $seo_title_en = isset($_POST['seo_title_en']) ? trim($_POST['seo_title_en']) : ($existing_product ? $existing_product['seo_title_en'] : '');
        $meta_description_ar = isset($_POST['meta_description_ar']) ? trim($_POST['meta_description_ar']) : ($existing_product ? $existing_product['meta_description_ar'] : '');
        $meta_description_en = isset($_POST['meta_description_en']) ? trim($_POST['meta_description_en']) : ($existing_product ? $existing_product['meta_description_en'] : '');
        $alt_text_ar = isset($_POST['alt_text_ar']) ? trim($_POST['alt_text_ar']) : ($existing_product ? $existing_product['alt_text_ar'] : '');
        $alt_text_en = isset($_POST['alt_text_en']) ? trim($_POST['alt_text_en']) : ($existing_product ? $existing_product['alt_text_en'] : '');

        $brand = isset($_POST['brand']) ? trim($_POST['brand']) : ($existing_product ? $existing_product['brand'] : '');
        $sku = isset($_POST['sku']) ? trim($_POST['sku']) : ($existing_product ? $existing_product['sku'] : '');
        $mpn = isset($_POST['mpn']) ? trim($_POST['mpn']) : ($existing_product ? $existing_product['mpn'] : '');
        $gtin = isset($_POST['gtin']) ? trim($_POST['gtin']) : ($existing_product ? $existing_product['gtin'] : '');
        $canonical_url = isset($_POST['canonical_url']) ? trim($_POST['canonical_url']) : ($existing_product ? $existing_product['canonical_url'] : '');

        $avail_input = isset($_POST['availability']) ? trim($_POST['availability']) : ($existing_product ? $existing_product['availability'] : 'InStock');
        $allowed_avail = ['InStock', 'OutOfStock', 'PreOrder'];
        $availability = in_array($avail_input, $allowed_avail, true) ? $avail_input : 'InStock';

        $cond_input = isset($_POST['condition_type']) ? trim($_POST['condition_type']) : ($existing_product ? $existing_product['condition_type'] : 'NewCondition');
        $allowed_cond = ['NewCondition', 'UsedCondition', 'RefurbishedCondition'];
        $condition_type = in_array($cond_input, $allowed_cond, true) ? $cond_input : 'NewCondition';

        $og_media_id = isset($_POST['og_media_id']) ? (!empty($_POST['og_media_id']) ? intval($_POST['og_media_id']) : null) : ($existing_product && $existing_product['og_media_id'] !== null ? intval($existing_product['og_media_id']) : null);

        // Technical Specifications
        if (isset($_POST['tech_spec_key_ar'])) {
            $tech_specs = [];
            if (is_array($_POST['tech_spec_key_ar'])) {
                $keys_ar = $_POST['tech_spec_key_ar'];
                $keys_en = $_POST['tech_spec_key_en'] ?? [];
                $vals_ar = $_POST['tech_spec_val_ar'] ?? [];
                $vals_en = $_POST['tech_spec_val_en'] ?? [];
                for ($i = 0; $i < count($keys_ar); $i++) {
                    $k_ar = trim($keys_ar[$i] ?? '');
                    $k_en = trim($keys_en[$i] ?? '');
                    $v_ar = trim($vals_ar[$i] ?? '');
                    $v_en = trim($vals_en[$i] ?? '');
                    if ($k_ar !== '' || $k_en !== '' || $v_ar !== '' || $v_en !== '') {
                        $tech_specs[] = [
                            'key_ar'   => $k_ar,
                            'key_en'   => $k_en,
                            'value_ar' => $v_ar,
                            'value_en' => $v_en
                        ];
                    }
                }
            }
            $tech_specs_json = !empty($tech_specs) ? json_encode($tech_specs, JSON_UNESCAPED_UNICODE) : null;
        } else {
            $tech_specs_json = $existing_product ? $existing_product['tech_specs'] : null;
        }

        // Related Product IDs
        if (isset($_POST['related_product_ids'])) {
            $related_product_ids = [];
            if (is_array($_POST['related_product_ids'])) {
                foreach ($_POST['related_product_ids'] as $rid) {
                    $clean_id = (int)$rid;
                    if ($clean_id > 0 && ($id <= 0 || $clean_id !== (int)$id) && !in_array($clean_id, $related_product_ids, true)) {
                        $related_product_ids[] = $clean_id;
                    }
                }
            }
            $related_ids_json = !empty($related_product_ids) ? json_encode($related_product_ids) : null;
        } else {
            $related_ids_json = $existing_product ? $existing_product['related_product_ids'] : null;
        }

        $slug = isset($_POST['slug']) ? trim($_POST['slug']) : ($existing_product ? $existing_product['slug'] : '');
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        if ($id > 0 && empty($slug)) {
            $slug = generate_unique_product_slug($name_en, $name_ar, $id, $pdo);
        } else if ($id > 0) {
            // Ensure unique
            $final_slug = $slug;
            $counter = 1;
            while (true) {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = :slug AND id != :id");
                $stmt_check->execute([':slug' => $final_slug, ':id' => $id]);
                if ((int)$stmt_check->fetchColumn() === 0) {
                    break;
                }
                $final_slug = $slug . '-' . $counter;
                $counter++;
            }
            $slug = $final_slug;
        }
        $short_intro_ar = isset($_POST['short_intro_ar']) ? trim($_POST['short_intro_ar']) : ($existing_product ? $existing_product['short_intro_ar'] : '');
        $short_intro_en = isset($_POST['short_intro_en']) ? trim($_POST['short_intro_en']) : ($existing_product ? $existing_product['short_intro_en'] : '');
        $catalog_pdf_url = isset($_POST['catalog_pdf_url']) ? trim($_POST['catalog_pdf_url']) : ($existing_product ? $existing_product['catalog_pdf_url'] : '');
        $focus_keyword_ar = isset($_POST['focus_keyword_ar']) ? trim($_POST['focus_keyword_ar']) : ($existing_product ? $existing_product['focus_keyword_ar'] : '');
        $focus_keyword_en = isset($_POST['focus_keyword_en']) ? trim($_POST['focus_keyword_en']) : ($existing_product ? $existing_product['focus_keyword_en'] : '');

        // Selling Points JSON
        if (isset($_POST['selling_point_ar'])) {
            $selling_points = [];
            if (is_array($_POST['selling_point_ar'])) {
                foreach ($_POST['selling_point_ar'] as $spidx => $sp_ar) {
                    $sp_ar = trim($sp_ar);
                    $sp_en = trim($_POST['selling_point_en'][$spidx] ?? '');
                    if ($sp_ar !== '' || $sp_en !== '') {
                        $selling_points[] = ['ar' => $sp_ar, 'en' => $sp_en];
                    }
                }
            }
            $selling_points_json = !empty($selling_points) ? json_encode($selling_points, JSON_UNESCAPED_UNICODE) : null;
        } else {
            $selling_points_json = $existing_product ? $existing_product['selling_points'] : null;
        }

        // Customer Benefits JSON
        if (isset($_POST['benefit_ar'])) {
            $benefits = [];
            if (is_array($_POST['benefit_ar'])) {
                foreach ($_POST['benefit_ar'] as $bidx => $b_ar) {
                    $b_ar = trim($b_ar);
                    $b_en = trim($_POST['benefit_en'][$bidx] ?? '');
                    if ($b_ar !== '' || $b_en !== '') {
                        $benefits[] = ['ar' => $b_ar, 'en' => $b_en];
                    }
                }
            }
            $benefits_json = !empty($benefits) ? json_encode($benefits, JSON_UNESCAPED_UNICODE) : null;
        } else {
            $benefits_json = $existing_product ? $existing_product['benefits'] : null;
        }

        // Industries Served JSON
        if (isset($_POST['industry_ar'])) {
            $industries = [];
            if (is_array($_POST['industry_ar'])) {
                foreach ($_POST['industry_ar'] as $iidx => $ind_ar) {
                    $ind_ar = trim($ind_ar);
                    $ind_en = trim($_POST['industry_en'][$iidx] ?? '');
                    if ($ind_ar !== '' || $ind_en !== '') {
                        $industries[] = ['ar' => $ind_ar, 'en' => $ind_en];
                    }
                }
            }
            $industries_json = !empty($industries) ? json_encode($industries, JSON_UNESCAPED_UNICODE) : null;
        } else {
            $industries_json = $existing_product ? $existing_product['industries_served'] : null;
        }

        // Related Projects IDs JSON
        if (false) { // Disabled: managed only from Project form
            $related_project_ids = [];
            if (is_array($_POST['related_project_ids'])) {
                foreach ($_POST['related_project_ids'] as $rpid) {
                    $cpid = (int)$rpid;
                    if ($cpid > 0 && !in_array($cpid, $related_project_ids, true)) $related_project_ids[] = $cpid;
                }
            }
            $related_project_ids_json = !empty($related_project_ids) ? json_encode($related_project_ids) : null;
        } else {
            $related_project_ids_json = $existing_product ? $existing_product['related_project_ids'] : null;
        }

        // Related Services IDs JSON
        if (isset($_POST['related_service_ids'])) {
            $related_service_ids = [];
            if (is_array($_POST['related_service_ids'])) {
                foreach ($_POST['related_service_ids'] as $rsid) {
                    $csid = (int)$rsid;
                    if ($csid > 0 && !in_array($csid, $related_service_ids, true)) $related_service_ids[] = $csid;
                }
            }
            $related_service_ids_json = !empty($related_service_ids) ? json_encode($related_service_ids) : null;
        } else {
            $related_service_ids_json = $existing_product ? $existing_product['related_service_ids'] : null;
        }

        // CTA Settings JSON
        if (isset($_POST['save_product'])) {
            $cta_settings = [
                'request_quote'    => isset($_POST['cta_request_quote']) ? 1 : 0,
                'whatsapp'         => isset($_POST['cta_whatsapp']) ? 1 : 0,
                'call_now'         => isset($_POST['cta_call_now']) ? 1 : 0,
                'download_catalog' => isset($_POST['cta_download_catalog']) ? 1 : 0,
                'site_visit'       => isset($_POST['cta_site_visit']) ? 1 : 0,
            ];
            $cta_settings_json = json_encode($cta_settings);
        } else {
            $cta_settings_json = $existing_product ? $existing_product['cta_settings'] : null;
        }

        if (empty($name_ar) || empty($name_en) || $stock < 0 || empty($category)) {
            $error_message = __('invalid_input');
        } elseif (!empty($canonical_url) && !filter_var($canonical_url, FILTER_VALIDATE_URL)) {
            $error_message = (get_current_lang() === 'ar' ? 'رابط Canonical غير صحيح.' : 'Invalid Canonical URL format.');
        } elseif (!empty($gtin) && !preg_match('/^\d{8}$|^\d{12}$|^\d{13}$|^\d{14}$/', $gtin)) {
            $error_message = (get_current_lang() === 'ar' ? 'رمز GTIN غير صالح. يجب أن يتكون من 8، 12، 13، أو 14 رقمًا.' : 'Invalid GTIN format. Must be 8, 12, 13, or 14 digits.');
        } else {
            $newFilesTracked = [];
            $uploaded_path = null;
            $old_image_path = '';

            try {
                if ($id > 0) {
                    // Fetch the complete product record before validating or moving replacement image
                    $stmt = $pdo->prepare("SELECT image_url, og_media_id FROM products WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $row_old = $stmt->fetch();
                    if ($row_old === false) {
                        throw new ProjectValidationException(__('product_not_found'));
                    }
                    $old_image_path = $row_old['image_url'] ?? '';
                    if ($og_media_id === null && !empty($row_old['og_media_id'])) {
                        $og_media_id = (int)$row_old['og_media_id'];
                    }
                }

                // Process main product image upload
                $fileInfo = $_FILES['image_file'] ?? null;
                if ($fileInfo && $fileInfo['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploaded_path = validate_and_upload_image($fileInfo, $newFilesTracked, 'products', 'product');
                }

                // Process OpenGraph image file upload if provided
                $ogFileInfo = $_FILES['og_image_file'] ?? null;
                if ($ogFileInfo && $ogFileInfo['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploaded_og_id = upload_to_media_library($pdo, $ogFileInfo, 'seo', $alt_text_ar, $alt_text_en, $seo_title_ar, $seo_title_en);
                    if ($uploaded_og_id) {
                        $og_media_id = $uploaded_og_id;
                    }
                }

                if ($id > 0) {
                    // Edit action
                    $image_url = ($uploaded_path !== null) ? $uploaded_path : $old_image_path;
                } else {
                    // Add action
                    if ($uploaded_path === null) {
                        throw new ProjectValidationException(__('image_required'));
                    }
                    $image_url = $uploaded_path;
                }

                $pdo->beginTransaction();

                if ($id > 0) {
                    // Update
                    $stmt = $pdo->prepare("UPDATE products SET 
                        name_ar = :name_ar, name_en = :name_en, slug = :slug,
                        short_intro_ar = :short_intro_ar, short_intro_en = :short_intro_en,
                        description_ar = :desc_ar, description_en = :desc_en,
                        price = :price, stock = :stock, category = :category, 
                        image_url = :image_url, dimensions = :dimensions, 
                        materials_ar = :materials_ar, materials_en = :materials_en,
                        selling_points = :selling_points, benefits = :benefits,
                        industries_served = :industries_served, catalog_pdf_url = :catalog_pdf_url,
                        is_featured = :is_featured, is_most_requested = :is_most_requested,
                        is_new = :is_new, is_active = :is_active, sort_order = :sort_order,
                        seo_title_ar = :seo_title_ar, seo_title_en = :seo_title_en,
                        meta_description_ar = :meta_desc_ar, meta_description_en = :meta_desc_en,
                        focus_keyword_ar = :focus_keyword_ar, focus_keyword_en = :focus_keyword_en,
                        alt_text_ar = :alt_text_ar, alt_text_en = :alt_text_en,
                        og_media_id = :og_media_id, brand = :brand, sku = :sku, mpn = :mpn, gtin = :gtin,
                        availability = :availability, condition_type = :condition_type, canonical_url = :canonical_url,
                        tech_specs = :tech_specs, cta_settings = :cta_settings,
                        related_product_ids = :related_product_ids,
                        related_project_ids = :related_project_ids,
                        related_service_ids = :related_service_ids,
                        dimensions_ar = :dimensions_ar,
                        dimensions_en = :dimensions_en,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id");
                    $stmt->execute([
                        ':name_ar' => $name_ar, ':name_en' => $name_en, ':slug' => $slug,
                        ':short_intro_ar' => $short_intro_ar, ':short_intro_en' => $short_intro_en,
                        ':desc_ar' => $desc_ar, ':desc_en' => $desc_en,
                        ':price' => $price, ':stock' => $stock, ':category' => $category,
                        ':image_url' => $image_url, ':dimensions' => $dimensions,
                        ':materials_ar' => $materials_ar, ':materials_en' => $materials_en,
                        ':selling_points' => $selling_points_json, ':benefits' => $benefits_json,
                        ':industries_served' => $industries_json, ':catalog_pdf_url' => $catalog_pdf_url,
                        ':is_featured' => $is_featured, ':is_most_requested' => $is_most_requested,
                        ':is_new' => $is_new, ':is_active' => $is_active, ':sort_order' => $sort_order,
                        ':seo_title_ar' => $seo_title_ar, ':seo_title_en' => $seo_title_en,
                        ':meta_desc_ar' => $meta_description_ar, ':meta_desc_en' => $meta_description_en,
                        ':focus_keyword_ar' => $focus_keyword_ar, ':focus_keyword_en' => $focus_keyword_en,
                        ':alt_text_ar' => $alt_text_ar, ':alt_text_en' => $alt_text_en,
                        ':og_media_id' => $og_media_id, ':brand' => $brand, ':sku' => $sku, ':mpn' => $mpn, ':gtin' => $gtin,
                        ':availability' => $availability, ':condition_type' => $condition_type, ':canonical_url' => $canonical_url,
                        ':tech_specs' => $tech_specs_json, ':cta_settings' => $cta_settings_json,
                        ':related_product_ids' => $related_ids_json,
                        ':related_project_ids' => $related_project_ids_json,
                        ':related_service_ids' => $related_service_ids_json,
                        ':dimensions_ar' => $dimensions_ar,
                        ':dimensions_en' => $dimensions_en,
                        ':id' => $id
                    ]);
                    
                    if ($stmt->rowCount() === 0) {
                        // Recheck whether the product still exists
                        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE id = :id");
                        $stmtCheck->execute([':id' => $id]);
                        if ((int)$stmtCheck->fetchColumn() !== 1) {
                            throw new ProjectValidationException(__('product_not_found'));
                        }
                    }
                } else {
                    $stmt = $pdo->prepare("INSERT INTO products 
                        (name_ar, name_en, slug, short_intro_ar, short_intro_en, description_ar, description_en, price, stock, category, image_url, dimensions, materials_ar, materials_en, selling_points, benefits, industries_served, catalog_pdf_url, is_featured, is_most_requested, is_new, is_active, sort_order, seo_title_ar, seo_title_en, meta_description_ar, meta_description_en, focus_keyword_ar, focus_keyword_en, alt_text_ar, alt_text_en, og_media_id, brand, sku, mpn, gtin, availability, condition_type, canonical_url, tech_specs, cta_settings, related_product_ids, related_project_ids, related_service_ids, dimensions_ar, dimensions_en, created_at, updated_at) 
                        VALUES (:name_ar, :name_en, :slug, :short_intro_ar, :short_intro_en, :desc_ar, :desc_en, :price, :stock, :category, :image_url, :dimensions, :materials_ar, :materials_en, :selling_points, :benefits, :industries_served, :catalog_pdf_url, :is_featured, :is_most_requested, :is_new, :is_active, :sort_order, :seo_title_ar, :seo_title_en, :meta_desc_ar, :meta_desc_en, :focus_keyword_ar, :focus_keyword_en, :alt_text_ar, :alt_text_en, :og_media_id, :brand, :sku, :mpn, :gtin, :availability, :condition_type, :canonical_url, :tech_specs, :cta_settings, :related_product_ids, :related_project_ids, :related_service_ids, :dimensions_ar, :dimensions_en, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                    $stmt->execute([
                        ':name_ar' => $name_ar, ':name_en' => $name_en, ':slug' => $slug,
                        ':short_intro_ar' => $short_intro_ar, ':short_intro_en' => $short_intro_en,
                        ':desc_ar' => $desc_ar, ':desc_en' => $desc_en,
                        ':price' => $price, ':stock' => $stock, ':category' => $category,
                        ':image_url' => $image_url, ':dimensions' => $dimensions,
                        ':materials_ar' => $materials_ar, ':materials_en' => $materials_en,
                        ':selling_points' => $selling_points_json, ':benefits' => $benefits_json,
                        ':industries_served' => $industries_json, ':catalog_pdf_url' => $catalog_pdf_url,
                        ':is_featured' => $is_featured, ':is_most_requested' => $is_most_requested,
                        ':is_new' => $is_new, ':is_active' => $is_active, ':sort_order' => $sort_order,
                        ':seo_title_ar' => $seo_title_ar, ':seo_title_en' => $seo_title_en,
                        ':meta_desc_ar' => $meta_description_ar, ':meta_desc_en' => $meta_description_en,
                        ':focus_keyword_ar' => $focus_keyword_ar, ':focus_keyword_en' => $focus_keyword_en,
                        ':alt_text_ar' => $alt_text_ar, ':alt_text_en' => $alt_text_en,
                        ':og_media_id' => $og_media_id, ':brand' => $brand, ':sku' => $sku, ':mpn' => $mpn, ':gtin' => $gtin,
                        ':availability' => $availability, ':condition_type' => $condition_type, ':canonical_url' => $canonical_url,
                        ':tech_specs' => $tech_specs_json, ':cta_settings' => $cta_settings_json,
                        ':related_product_ids' => $related_ids_json,
                        ':related_project_ids' => $related_project_ids_json,
                        ':related_service_ids' => $related_service_ids_json,
                        ':dimensions_ar' => $dimensions_ar,
                        ':dimensions_en' => $dimensions_en
                    ]);
                    $new_product_id = (int)$pdo->lastInsertId();
                    if (empty($slug)) {
                        $generated_slug = generate_unique_product_slug($name_en, $name_ar, $new_product_id, $pdo);
                        $upStmt = $pdo->prepare("UPDATE products SET slug = :slug WHERE id = :id");
                        $upStmt->execute([':slug' => $generated_slug, ':id' => $new_product_id]);
                    }
                }

                $target_pid = ($id > 0) ? $id : (int)$pdo->lastInsertId();

                // 1. Ensure primary image exists in product_images
                if (!empty($image_url)) {
                    $chk_pri = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = :pid AND is_primary = 1");
                    $chk_pri->execute([':pid' => $target_pid]);
                    if ((int)$chk_pri->fetchColumn() === 0) {
                        $ins_pri = $pdo->prepare("INSERT INTO product_images (product_id, image_path, alt_ar, alt_en, is_primary, is_active) VALUES (:pid, :path, :alt_ar, :alt_en, 1, 1)");
                        $ins_pri->execute([
                            ':pid' => $target_pid,
                            ':path' => $image_url,
                            ':alt_ar' => $alt_text_ar,
                            ':alt_en' => $alt_text_en
                        ]);
                    } elseif (!empty($uploaded_path)) {
                        $upd_pri = $pdo->prepare("UPDATE product_images SET image_path = :path WHERE product_id = :pid AND is_primary = 1");
                        $upd_pri->execute([':path' => $uploaded_path, ':pid' => $target_pid]);
                    }
                }

                // 2. Process multi-file gallery image uploads (Maximum 4 additional images limit)
                if (isset($_FILES['gallery_files']) && !empty($_FILES['gallery_files']['name'][0])) {
                    $g_files = $_FILES['gallery_files'];
                    $total_g = count($g_files['name']);

                    // Count current non-primary gallery images
                    $del_count = 0;
                    if (!empty($_POST['delete_gallery_ids']) && is_array($_POST['delete_gallery_ids'])) {
                        $del_count = count($_POST['delete_gallery_ids']);
                    }
                    
                    $stmt_count_existing = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = :pid AND is_primary = 0");
                    $stmt_count_existing->execute([':pid' => $target_pid]);
                    $current_non_primary = (int)$stmt_count_existing->fetchColumn();

                    $net_existing = max(0, $current_non_primary - $del_count);
                    if (($net_existing + $total_g) > 4) {
                        throw new ProjectValidationException(__('product_gallery_limit_exceeded'));
                    }

                    for ($gi = 0; $gi < $total_g; $gi++) {
                        if ($g_files['error'][$gi] === UPLOAD_ERR_OK) {
                            $single_file = [
                                'name'     => $g_files['name'][$gi],
                                'type'     => $g_files['type'][$gi],
                                'tmp_name' => $g_files['tmp_name'][$gi],
                                'error'    => $g_files['error'][$gi],
                                'size'     => $g_files['size'][$gi]
                            ];
                            $g_uploaded = validate_and_upload_image($single_file, $newFilesTracked, 'products', 'product');
                            if ($g_uploaded) {
                                $ins_g = $pdo->prepare("INSERT INTO product_images (product_id, image_path, alt_ar, alt_en, is_primary, is_active) VALUES (:pid, :path, :alt_ar, :alt_en, 0, 1)");
                                $ins_g->execute([
                                    ':pid' => $target_pid,
                                    ':path' => $g_uploaded,
                                    ':alt_ar' => $name_ar,
                                    ':alt_en' => $name_en
                                ]);
                            }
                        }
                    }
                }

                // 3. Process gallery image deletion
                if (!empty($_POST['delete_gallery_ids']) && is_array($_POST['delete_gallery_ids'])) {
                    foreach ($_POST['delete_gallery_ids'] as $del_g_id) {
                        $del_g_id = (int)$del_g_id;
                        $del_stmt = $pdo->prepare("DELETE FROM product_images WHERE id = :id AND product_id = :pid AND is_primary = 0");
                        $del_stmt->execute([':id' => $del_g_id, ':pid' => $target_pid]);
                    }
                }

                // 4. Process setting primary gallery image
                if (!empty($_POST['set_primary_img_id'])) {
                    $new_pri_id = (int)$_POST['set_primary_img_id'];
                    $reset_pri = $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = :pid");
                    $reset_pri->execute([':pid' => $target_pid]);

                    $set_pri = $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = :id AND product_id = :pid");
                    $set_pri->execute([':id' => $new_pri_id, ':pid' => $target_pid]);

                    $get_new_path = $pdo->prepare("SELECT image_path FROM product_images WHERE id = :id");
                    $get_new_path->execute([':id' => $new_pri_id]);
                    $new_main_path = $get_new_path->fetchColumn();
                    if ($new_main_path) {
                        $upd_prod_main = $pdo->prepare("UPDATE products SET image_url = :path, updated_at = CURRENT_TIMESTAMP WHERE id = :pid");
                        $upd_prod_main->execute([':path' => $new_main_path, ':pid' => $target_pid]);
                    }
                }

                // 5. Process new colors
                if (!empty($_POST['new_color_name_ar']) && is_array($_POST['new_color_name_ar'])) {
                    foreach ($_POST['new_color_name_ar'] as $cidx => $cname_ar) {
                        $cname_ar = trim($cname_ar);
                        $cname_en = trim($_POST['new_color_name_en'][$cidx] ?? $cname_ar);
                        $chex     = trim($_POST['new_color_hex'][$cidx] ?? '');
                        if (!empty($cname_ar) && !empty($cname_en)) {
                            $ins_col = $pdo->prepare("INSERT INTO product_colors (product_id, name_ar, name_en, hex_value, is_active) VALUES (:pid, :name_ar, :name_en, :hex, 1)");
                            $ins_col->execute([
                                ':pid' => $target_pid,
                                ':name_ar' => $cname_ar,
                                ':name_en' => $cname_en,
                                ':hex' => !empty($chex) ? $chex : null
                            ]);
                        }
                    }
                }

                // 6. Process color deletion
                if (!empty($_POST['delete_color_ids']) && is_array($_POST['delete_color_ids'])) {
                    foreach ($_POST['delete_color_ids'] as $del_c_id) {
                        $del_c_id = (int)$del_c_id;
                        $del_c_stmt = $pdo->prepare("DELETE FROM product_colors WHERE id = :id AND product_id = :pid");
                        $del_c_stmt->execute([':id' => $del_c_id, ':pid' => $target_pid]);
                    }
                }

                // 7. Process new models
                if (!empty($_POST['new_model_name_ar']) && is_array($_POST['new_model_name_ar'])) {
                    foreach ($_POST['new_model_name_ar'] as $midx => $mname_ar) {
                        $mname_ar = trim($mname_ar);
                        $mname_en = trim($_POST['new_model_name_en'][$midx] ?? $mname_ar);
                        $mdesc_ar = trim($_POST['new_model_desc_ar'][$midx] ?? '');
                        if (!empty($mname_ar) && !empty($mname_en)) {
                            $ins_mod = $pdo->prepare("INSERT INTO product_models (product_id, name_ar, name_en, description_ar, is_active) VALUES (:pid, :name_ar, :name_en, :desc_ar, 1)");
                            $ins_mod->execute([
                                ':pid' => $target_pid,
                                ':name_ar' => $mname_ar,
                                ':name_en' => $mname_en,
                                ':desc_ar' => !empty($mdesc_ar) ? $mdesc_ar : null
                            ]);
                        }
                    }
                }

                // 8. Process model deletion
                if (!empty($_POST['delete_model_ids']) && is_array($_POST['delete_model_ids'])) {
                    foreach ($_POST['delete_model_ids'] as $del_m_id) {
                        $del_m_id = (int)$del_m_id;
                        $del_m_stmt = $pdo->prepare("DELETE FROM product_models WHERE id = :id AND product_id = :pid");
                        $del_m_stmt->execute([':id' => $del_m_id, ':pid' => $target_pid]);
                    }
                }

                // 9. Process Detail Cards (Suitable For & Why Product) Updates and Additions
                if (!empty($_POST['card_section']) && is_array($_POST['card_section'])) {
                    foreach ($_POST['card_section'] as $k => $c_section) {
                        $c_section = trim($c_section);
                        if (!in_array($c_section, ['suitable_for', 'why_product'], true)) continue;

                        $c_id       = isset($_POST['card_id'][$k]) ? (int)$_POST['card_id'][$k] : 0;
                        $c_title_ar = trim($_POST['card_title_ar'][$k] ?? '');
                        $c_title_en = trim($_POST['card_title_en'][$k] ?? '');
                        $c_desc_ar  = trim($_POST['card_desc_ar'][$k] ?? '');
                        $c_desc_en  = trim($_POST['card_desc_en'][$k] ?? '');
                        $c_icon     = validate_icon_name($_POST['card_icon'][$k] ?? 'shield');
                        $c_sort     = isset($_POST['card_sort'][$k]) ? (int)$_POST['card_sort'][$k] : 0;
                        $c_active   = isset($_POST['card_active'][$k]) ? (int)$_POST['card_active'][$k] : 1;

                        if (empty($c_title_ar) && empty($c_title_en)) continue;
                        if (empty($c_title_ar)) $c_title_ar = $c_title_en;
                        if (empty($c_title_en)) $c_title_en = $c_title_ar;

                        if ($c_id > 0) {
                            // Update existing card
                            $upd_card = $pdo->prepare("UPDATE product_detail_cards SET 
                                title_ar = :tar, title_en = :ten, 
                                description_ar = :dar, description_en = :den, 
                                icon_name = :icon, sort_order = :sort, is_active = :active,
                                updated_at = CURRENT_TIMESTAMP
                                WHERE id = :cid AND product_id = :pid");
                            $upd_card->execute([
                                ':tar'    => $c_title_ar,
                                ':ten'    => $c_title_en,
                                ':dar'    => !empty($c_desc_ar) ? $c_desc_ar : null,
                                ':den'    => !empty($c_desc_en) ? $c_desc_en : null,
                                ':icon'   => $c_icon,
                                ':sort'   => $c_sort,
                                ':active' => $c_active,
                                ':cid'    => $c_id,
                                ':pid'    => $target_pid
                            ]);
                        } else {
                            // Insert new card
                            $ins_card = $pdo->prepare("INSERT INTO product_detail_cards 
                                (product_id, section_type, title_ar, title_en, description_ar, description_en, icon_name, sort_order, is_active) 
                                VALUES (:pid, :stype, :tar, :ten, :dar, :den, :icon, :sort, :active)");
                            $ins_card->execute([
                                ':pid'    => $target_pid,
                                ':stype'  => $c_section,
                                ':tar'    => $c_title_ar,
                                ':ten'    => $c_title_en,
                                ':dar'    => !empty($c_desc_ar) ? $c_desc_ar : null,
                                ':den'    => !empty($c_desc_en) ? $c_desc_en : null,
                                ':icon'   => $c_icon,
                                ':sort'   => $c_sort,
                                ':active' => $c_active
                            ]);
                        }
                    }
                }

                // 10. Process Detail Cards Deletion
                if (!empty($_POST['delete_card_ids']) && is_array($_POST['delete_card_ids'])) {
                    foreach ($_POST['delete_card_ids'] as $del_card_id) {
                        $del_card_id = (int)$del_card_id;
                        $del_card_stmt = $pdo->prepare("DELETE FROM product_detail_cards WHERE id = :id AND product_id = :pid");
                        $del_card_stmt->execute([':id' => $del_card_id, ':pid' => $target_pid]);
                    }
                }

                $pdo->commit();

                // Delete old file if edit commit was successful, and path has changed
                $cleanupSuccess = true;
                if ($id > 0 && $uploaded_path !== null && !empty($old_image_path) && $old_image_path !== $uploaded_path) {
                    $cleanupSuccess = safe_delete_file($old_image_path, 'products');
                }

                if (!$cleanupSuccess) {
                    set_flash('success', ($id > 0 ? __('product_updated') : __('product_added')) . ' ' . __('cleanup_incomplete_warning'));
                } else {
                    set_flash('success', $id > 0 ? __('product_updated') : __('product_added'));
                }
                header("Location: products.php");
                exit;

            } catch (ProjectValidationException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                rollback_cleanup_files($newFilesTracked);
                $error_message = $ex->getMessage();
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                rollback_cleanup_files($newFilesTracked);
                error_log("Database error in products save: " . $ex->getMessage());
                $error_message = __('database_operation_failed');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                rollback_cleanup_files($newFilesTracked);
                error_log("Unexpected error in products save: " . $ex->getMessage());
                $error_message = __('unexpected_operation_failed');
            }
        }
    }
}

// Fetch Product for Editing
$product_to_edit = null;
$edit_gallery_images = [];
$edit_suitable_cards = [];
$edit_why_cards = [];
$edit_tech_specs = [];
$edit_related_ids = [];
$edit_selling_points = [];
$edit_benefits = [];
$edit_industries = [];
$edit_related_project_ids = [];
$edit_related_service_ids = [];
$edit_cta_settings = [];

if (($action === 'edit' || $action === 'add') && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $product_to_edit = $stmt->fetch();
    if ($product_to_edit) {
        $edit_gallery_images = get_admin_product_images($pdo, $id);
        $edit_suitable_cards = get_admin_product_detail_cards($pdo, $id, 'suitable_for');
        $edit_why_cards = get_admin_product_detail_cards($pdo, $id, 'why_product');
        $edit_tech_specs = get_product_tech_specs($product_to_edit);
        $edit_selling_points = get_product_json_field($product_to_edit, 'selling_points');
        $edit_benefits = get_product_json_field($product_to_edit, 'benefits');
        $edit_industries = get_product_json_field($product_to_edit, 'industries_served');
        $edit_related_project_ids = array_map('intval', get_product_json_field($product_to_edit, 'related_project_ids'));
        $edit_related_service_ids = array_map('intval', get_product_json_field($product_to_edit, 'related_service_ids'));
        $edit_cta_settings = get_product_json_field($product_to_edit, 'cta_settings');
        if (!empty($product_to_edit['related_product_ids'])) {
            $raw_r = json_decode($product_to_edit['related_product_ids'], true);
            if (is_array($raw_r)) {
                $edit_related_ids = array_map('intval', $raw_r);
            }
        }
    }
}

// Fetch all active products except current one for Related Products selector
$all_active_products = [];
try {
    $ap_stmt = $pdo->prepare("SELECT id, name_ar, name_en, category, image_url FROM products WHERE is_active = 1 AND id != :curr_id ORDER BY name_ar ASC");
    $ap_stmt->execute([':curr_id' => $id]);
    $all_active_products = $ap_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_active_products = [];
}

// Fetch all active portfolio projects for Related Projects selector
$all_active_projects = [];
try {
    $prj_stmt = $pdo->query("SELECT id, title_ar, title_en, main_image_url FROM projects WHERE is_active = 1 ORDER BY title_ar ASC");
    $all_active_projects = $prj_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $all_active_projects = [];
}

// Fetch all active racking services for Related Services selector
$all_active_services = [];
try {
    $srv_stmt = $pdo->query("SELECT id, title_ar, title_en FROM services WHERE is_active = 1 ORDER BY title_ar ASC");
    $all_active_services = $srv_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $all_active_services = [];
}

// Capture Filter GET Parameters
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_category = isset($_GET['category']) ? trim($_GET['category']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_featured = isset($_GET['featured']) ? trim($_GET['featured']) : '';

// Build dynamic WHERE clause
$where_clauses = [];
$params = [];

if (!empty($filter_search)) {
    $where_clauses[] = "(name_ar LIKE :search OR name_en LIKE :search OR code LIKE :search OR id = :exact_id)";
    $params[':search'] = '%' . $filter_search . '%';
    $params[':exact_id'] = is_numeric($filter_search) ? (int)$filter_search : 0;
}

if (!empty($filter_category)) {
    $where_clauses[] = "category = :filter_category";
    $params[':filter_category'] = $filter_category;
}

if ($filter_status === 'active') {
    $where_clauses[] = "is_active = 1";
} elseif ($filter_status === 'inactive') {
    $where_clauses[] = "is_active = 0";
} elseif ($filter_status === 'out_of_stock') {
    $where_clauses[] = "stock = 0";
}

if ($filter_featured === 'yes') {
    $where_clauses[] = "is_featured = 1";
}

$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Helper for product thumbnail rendering
function get_product_thumb_html($img_url) {
    if (empty($img_url)) {
        return '<div class="product-cell-thumb-placeholder"><i data-lucide="package" style="width:20px;"></i></div>';
    }
    $clean_path = ltrim($img_url, '/');
    if (strpos($clean_path, '../') === 0) {
        $clean_path = substr($clean_path, 3);
    }
    $local = dirname(__DIR__) . '/' . $clean_path;
    if (file_exists($local)) {
        return '<img src="../' . htmlspecialchars($clean_path) . '" class="product-cell-thumb" alt="Product thumbnail" loading="lazy">';
    }
    return '<div class="product-cell-thumb-placeholder"><i data-lucide="package" style="width:20px;"></i></div>';
}

try {
    $prod_stmt = $pdo->prepare("SELECT * FROM products $where_sql ORDER BY sort_order ASC, id DESC");
    $prod_stmt->execute($params);
    $products = $prod_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary Statistics
    $stat_total = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $stat_active = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
    $stat_featured = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_featured = 1")->fetchColumn();
    $stat_out_of_stock = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn();

    // Categories map
    $cat_list_stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
    $categories = $cat_list_stmt->fetchAll();
    $categories_map = [];
    foreach ($categories as $c) {
        $categories_map[$c['code']] = (get_current_lang() == 'ar') ? $c['name_ar'] : $c['name_en'];
    }

    $media_stmt = $pdo->query("SELECT id, filename, file_path, folder FROM media_library ORDER BY id DESC LIMIT 200");
    $media_list = $media_stmt->fetchAll(PDO::FETCH_ASSOC);
    $media_path_map = [];
    foreach ($media_list as $m) {
        $media_path_map[$m['id']] = $m['file_path'];
    }
} catch (PDOException $e) {
    $products = [];
    $categories = [];
    $categories_map = [];
    $media_list = [];
    $media_path_map = [];
    $stat_total = 0;
    $stat_active = 0;
    $stat_featured = 0;
    $stat_out_of_stock = 0;
}

$page_title = __('admin_manage_products');
$lang = get_current_lang();
require_once dirname(__DIR__) . '/includes/admin-header.php';
require_once dirname(__DIR__) . '/includes/admin-sidebar.php';
?>

<main class="admin-content">
    <!-- Compact Page Header -->
    <header class="admin-header" style="padding-bottom: 12px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                <a href="index.php" style="color: var(--text-muted); text-decoration: none;"><?php echo htmlspecialchars(__('brand_name')); ?></a>
                <span>/</span>
                <span><?php echo $lang === 'ar' ? 'الكتالوج' : 'Catalog'; ?></span>
                <span>/</span>
                <span><?php echo htmlspecialchars($page_title); ?></span>
            </div>
            <h1 style="font-size: 22px; font-weight: 800; margin: 0;"><?php echo htmlspecialchars($page_title); ?></h1>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a href="categories.php" class="btn btn-secondary btn-sm" style="font-size: 12.5px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 6px;">
                <i data-lucide="folder" style="width:15px; height:15px;"></i>
                <span><?php echo $lang === 'ar' ? 'إدارة التصنيفات' : 'Categories'; ?></span>
            </a>
            <a href="../products" target="_blank" class="btn btn-secondary btn-sm" style="font-size: 12.5px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 6px;">
                <i data-lucide="external-link" style="width:15px; height:15px;"></i>
                <span><?php echo $lang === 'ar' ? 'معاينة الكتالوج' : 'View Catalog'; ?></span>
            </a>
            <a href="products.php?action=add" class="btn btn-primary btn-sm" style="font-size: 12.5px; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                <i data-lucide="plus" style="width:16px; height:16px;"></i>
                <span><?php echo __('admin_add_product'); ?></span>
            </a>
        </div>
    </header>

    <!-- Message Alerts -->
    <?php
    $flashError = get_flash('error');
    $flashSuccess = get_flash('success');
    if ($flashError): ?>
        <div class="alert-danger" style="background-color: var(--danger-light); color: var(--danger); padding: 12px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 600;">
            <?= htmlspecialchars($flashError) ?>
        </div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
        <div class="alert-success" style="background-color: var(--success-light); color: var(--success); padding: 12px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 600;">
            <?= htmlspecialchars($flashSuccess) ?>
        </div>
    <?php endif; ?>

    <!-- Products Summary Strip -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <?php
        render_admin_kpi_card($lang === 'ar' ? 'إجمالي المنتجات' : 'Total Products', $stat_total, 'package', 'blue');
        render_admin_kpi_card($lang === 'ar' ? 'المنتجات المفعلة' : 'Active Products', $stat_active, 'check-circle', 'green');
        render_admin_kpi_card($lang === 'ar' ? 'المنتجات المميزة' : 'Featured Products', $stat_featured, 'star', 'amber');
        render_admin_kpi_card($lang === 'ar' ? 'نفذت من المخزون' : 'Out of Stock', $stat_out_of_stock, 'alert-triangle', 'red');
        ?>
    </div>

    <!-- Adaptive Filter Controls Bar -->
    <section class="products-filter-bar">
        <form method="GET" action="products.php" class="products-filter-form">
            <div class="filter-input-search">
                <i data-lucide="search"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="<?php echo $lang === 'ar' ? 'ابحث باسم المنتج، الكود، أو المعرف...' : 'Search by name, SKU, or ID...'; ?>">
            </div>

            <select name="category" class="filter-select" onchange="this.form.submit()">
                <option value=""><?php echo $lang === 'ar' ? 'جميع التصنيفات' : 'All Categories'; ?></option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['code']); ?>" <?php echo $filter_category === $cat['code'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lang === 'ar' ? $cat['name_ar'] : $cat['name_en']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value=""><?php echo $lang === 'ar' ? 'جميع الحالات' : 'All Statuses'; ?></option>
                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'مفعل فقط' : 'Active Only'; ?></option>
                <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'معطل فقط' : 'Inactive Only'; ?></option>
                <option value="out_of_stock" <?php echo $filter_status === 'out_of_stock' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'نفذ من المخزون' : 'Out of Stock'; ?></option>
            </select>

            <select name="featured" class="filter-select" onchange="this.form.submit()">
                <option value=""><?php echo $lang === 'ar' ? 'المميزة والكل' : 'All Products'; ?></option>
                <option value="yes" <?php echo $filter_featured === 'yes' ? 'selected' : ''; ?>><?php echo $lang === 'ar' ? 'المميزة فقط ⭐' : 'Featured Only ⭐'; ?></option>
            </select>

            <?php if (!empty($filter_search) || !empty($filter_category) || !empty($filter_status) || !empty($filter_featured)): ?>
                <a href="products.php" class="filter-btn-reset">
                    <i data-lucide="rotate-ccw" style="width:14px; height:14px;"></i>
                    <span><?php echo $lang === 'ar' ? 'إعادة ضبط' : 'Reset'; ?></span>
                </a>
            <?php endif; ?>
        </form>
    </section>

    <!-- Main Content Presentation -->
    <?php if (count($products) > 0): ?>
        <!-- Desktop / Tablet View -->
        <div class="products-desktop-table-card">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><?php echo $lang === 'ar' ? 'تفاصيل المنتج' : 'Product Identity'; ?></th>
                        <th><?php echo __('p_stock'); ?></th>
                        <th><?php echo __('p_category'); ?></th>
                        <th style="text-align: center;"><?php echo $lang === 'ar' ? 'المؤشرات' : 'Indicators'; ?></th>
                        <th style="text-align: center; width: 80px;"><?php echo __('sort_order'); ?></th>
                        <th style="text-align: center;"><?php echo __('admin_actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $prod): ?>
                        <tr>
                            <td>
                                <div class="product-cell-identity">
                                    <?php echo get_product_thumb_html($prod['image_url']); ?>
                                    <div class="product-cell-titles">
                                        <span class="product-cell-title-ar"><?php echo htmlspecialchars($prod['name_ar']); ?></span>
                                        <span class="product-cell-title-en"><?php echo htmlspecialchars($prod['name_en']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($prod['stock'] > 0): ?>
                                    <span class="nav-badge badge-success"><?php echo $prod['stock']; ?> <?php echo $lang === 'ar' ? 'متوفر' : 'In Stock'; ?></span>
                                <?php else: ?>
                                    <span class="nav-badge badge-danger"><?php echo $lang === 'ar' ? '0 (نفذ)' : 'Out of Stock'; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="nav-badge" style="background: var(--admin-bg-subtle); color: var(--admin-text-main); font-weight: 600;">
                                    <?php echo htmlspecialchars(isset($categories_map[$prod['category']]) ? $categories_map[$prod['category']] : $prod['category']); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                    <!-- Featured -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_flag">
                                        <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                                        <input type="hidden" name="flag" value="is_featured">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <button type="submit" class="product-mobile-toggle-btn" title="<?php echo __('toggle_featured'); ?>" style="color: <?php echo (!empty($prod['is_featured']) && $prod['is_featured']) ? '#f59e0b' : '#cbd5e1'; ?>;">
                                            <i data-lucide="star" style="width: 17px;"></i>
                                        </button>
                                    </form>
                                    <!-- Most Requested -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_flag">
                                        <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                                        <input type="hidden" name="flag" value="is_most_requested">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <button type="submit" class="product-mobile-toggle-btn" title="<?php echo __('toggle_most_requested'); ?>" style="color: <?php echo (!empty($prod['is_most_requested']) && $prod['is_most_requested']) ? '#f97316' : '#cbd5e1'; ?>;">
                                            <i data-lucide="flame" style="width: 17px;"></i>
                                        </button>
                                    </form>
                                    <!-- New -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_flag">
                                        <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                                        <input type="hidden" name="flag" value="is_new">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <button type="submit" class="product-mobile-toggle-btn" title="<?php echo __('new_product'); ?>" style="color: <?php echo (!empty($prod['is_new']) && $prod['is_new']) ? '#3b82f6' : '#cbd5e1'; ?>;">
                                            <i data-lucide="sparkles" style="width: 17px;"></i>
                                        </button>
                                    </form>
                                    <!-- Active -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_flag">
                                        <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                                        <input type="hidden" name="flag" value="is_active">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <button type="submit" class="product-mobile-toggle-btn" title="<?php echo __('toggle_active'); ?>" style="color: <?php echo (!isset($prod['is_active']) || $prod['is_active']) ? '#10b981' : '#ef4444'; ?>;">
                                            <i data-lucide="<?php echo (!isset($prod['is_active']) || $prod['is_active']) ? 'eye' : 'eye-off'; ?>" style="width: 17px;"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update_sort_order">
                                    <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="number" name="sort_order" min="0" value="<?php echo isset($prod['sort_order']) ? intval($prod['sort_order']) : 0; ?>" style="width: 55px; text-align: center; padding: 4px; border: 1px solid var(--admin-border-color); border-radius: var(--radius-sm);" onchange="this.form.submit()">
                                </form>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                    <a href="products.php?action=edit&id=<?php echo $prod['id']; ?>" class="btn btn-secondary btn-sm" style="font-size: 12px; padding: 5px 10px;">
                                        <i data-lucide="edit-3" style="width: 14px;"></i>
                                        <span><?php echo $lang === 'ar' ? 'تعديل' : 'Edit'; ?></span>
                                    </a>
                                    <form method="POST" style="display:inline; margin: 0;" onsubmit="return confirm('<?php echo __('product_delete_confirm'); ?>')">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" style="font-size: 12px; padding: 5px 10px; color: var(--admin-danger); border-color: var(--admin-danger-light);">
                                            <i data-lucide="trash-2" style="width: 14px;"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Touch Management Cards (< 767px) -->
        <div class="products-mobile-card-list">
            <?php foreach ($products as $prod): ?>
                <div class="product-mobile-card">
                    <div class="product-mobile-card-header">
                        <?php echo get_product_thumb_html($prod['image_url']); ?>
                        <div class="product-cell-titles">
                            <span class="product-cell-title-ar"><?php echo htmlspecialchars($prod['name_ar']); ?></span>
                            <span class="product-cell-title-en"><?php echo htmlspecialchars($prod['name_en']); ?></span>
                        </div>
                    </div>

                    <div class="product-mobile-card-meta">
                        <div>
                            <span class="nav-badge" style="background: var(--admin-bg-subtle); color: var(--admin-text-main); font-weight: 600;">
                                <?php echo htmlspecialchars(isset($categories_map[$prod['category']]) ? $categories_map[$prod['category']] : $prod['category']); ?>
                            </span>
                        </div>
                        <div>
                            <?php if ($prod['stock'] > 0): ?>
                                <span class="nav-badge badge-success"><?php echo $prod['stock']; ?> <?php echo $lang === 'ar' ? 'متوفر' : 'In Stock'; ?></span>
                            <?php else: ?>
                                <span class="nav-badge badge-danger"><?php echo $lang === 'ar' ? '0 (نفذ)' : 'Out of Stock'; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Flags & Toggles Row -->
                    <div class="product-mobile-toggles-row">
                        <!-- Featured -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_flag">
                            <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                            <input type="hidden" name="flag" value="is_featured">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" class="product-mobile-toggle-btn" title="<?php echo __('toggle_featured'); ?>" style="color: <?php echo (!empty($prod['is_featured']) && $prod['is_featured']) ? '#f59e0b' : '#cbd5e1'; ?>;">
                                <i data-lucide="star" style="width: 20px; height: 20px;"></i>
                            </button>
                        </form>
                        <!-- Most Requested -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_flag">
                            <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                            <input type="hidden" name="flag" value="is_most_requested">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" class="product-mobile-toggle-btn" title="<?php echo __('toggle_most_requested'); ?>" style="color: <?php echo (!empty($prod['is_most_requested']) && $prod['is_most_requested']) ? '#f97316' : '#cbd5e1'; ?>;">
                                <i data-lucide="flame" style="width: 20px; height: 20px;"></i>
                            </button>
                        </form>
                        <!-- New -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_flag">
                            <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                            <input type="hidden" name="flag" value="is_new">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" class="product-mobile-toggle-btn" title="<?php echo __('new_product'); ?>" style="color: <?php echo (!empty($prod['is_new']) && $prod['is_new']) ? '#3b82f6' : '#cbd5e1'; ?>;">
                                <i data-lucide="sparkles" style="width: 20px; height: 20px;"></i>
                            </button>
                        </form>
                        <!-- Active -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_flag">
                            <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                            <input type="hidden" name="flag" value="is_active">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" class="product-mobile-toggle-btn" title="<?php echo __('toggle_active'); ?>" style="color: <?php echo (!isset($prod['is_active']) || $prod['is_active']) ? '#10b981' : '#ef4444'; ?>;">
                                <i data-lucide="<?php echo (!isset($prod['is_active']) || $prod['is_active']) ? 'eye' : 'eye-off'; ?>" style="width: 20px; height: 20px;"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Primary Actions Row -->
                    <div class="product-mobile-actions-row">
                        <a href="products.php?action=edit&id=<?php echo $prod['id']; ?>" class="btn btn-primary btn-sm" style="flex: 1; justify-content: center; font-size: 13px; font-weight: 700;">
                            <i data-lucide="edit-3" style="width: 16px;"></i>
                            <span><?php echo $lang === 'ar' ? 'تعديل المنتج' : 'Edit Product'; ?></span>
                        </a>
                        <form method="POST" style="display:inline; margin: 0;" onsubmit="return confirm('<?php echo __('product_delete_confirm'); ?>')">
                            <input type="hidden" name="action" value="delete_product">
                            <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--admin-danger); border-color: var(--admin-danger-light); padding: 8px 14px;">
                                <i data-lucide="trash-2" style="width: 16px;"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div style="background: var(--admin-bg-card); border: 1px solid var(--admin-border-color); border-radius: var(--radius-lg); padding: 48px 24px; text-align: center;">
            <i data-lucide="package-open" style="width: 48px; height: 48px; color: var(--admin-text-subtle); margin-bottom: 12px; display: inline-block;"></i>
            <?php if (!empty($filter_search) || !empty($filter_category) || !empty($filter_status) || !empty($filter_featured)): ?>
                <h3 style="font-size: 16px; margin: 0 0 6px 0; color: var(--admin-text-main);"><?php echo $lang === 'ar' ? 'لم يتم العثور على منتجات مطابقة' : 'No matching products found'; ?></h3>
                <p style="font-size: 13.5px; color: var(--admin-text-muted); margin: 0 0 16px 0;"><?php echo $lang === 'ar' ? 'جرب تغيير خيارات التصفية أو كلمة البحث' : 'Try adjusting your search query or filter options'; ?></p>
                <a href="products.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                    <i data-lucide="rotate-ccw"></i>
                    <span><?php echo $lang === 'ar' ? 'إعادة ضبط التصفية' : 'Reset Filters'; ?></span>
                </a>
            <?php else: ?>
                <h3 style="font-size: 16px; margin: 0 0 6px 0; color: var(--admin-text-main);"><?php echo $lang === 'ar' ? 'لم يتم تسجيل أي منتجات بعد' : 'No products registered yet'; ?></h3>
                <p style="font-size: 13.5px; color: var(--admin-text-muted); margin: 0 0 16px 0;"><?php echo $lang === 'ar' ? 'ابدأ بإضافة أول منتج لكتالوج عالم الرفوف' : 'Get started by adding your first product to the catalog'; ?></p>
                <a href="products.php?action=add" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                    <i data-lucide="plus"></i>
                    <span><?php echo __('admin_add_product'); ?></span>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

        <!-- Add / Edit Modal Overlay (Enterprise Landing Page CMS UI) -->
        <?php if ($action === 'add' || ($action === 'edit' && $product_to_edit)): ?>
            <div class="admin-modal enterprise-cms-modal" id="productModal">
                <div class="admin-modal-content enterprise-modal-content" style="max-width: 1100px; width: 95%; height: 92vh; display: flex; flex-direction: column; padding: 0; overflow: hidden; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);">
                    
                    <!-- Sticky Enterprise Top Bar -->
                    <div class="enterprise-top-bar" style="position: sticky; top: 0; z-index: 100; background: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <a href="products.php" class="icon-btn" title="<?php echo __('admin_cancel'); ?>" style="font-size: 18px;"><i data-lucide="x"></i></a>
                            <div>
                                <h2 style="margin: 0; font-size: 16px; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                                    <i data-lucide="layout" style="color: #2563eb; width: 20px; height: 20px;"></i>
                                    <span><?php echo $action === 'edit' ? ($lang === 'ar' ? 'محرر صفحة هبوط المنتج Enterprise' : 'Enterprise Product Landing Page Editor') : ($lang === 'ar' ? 'إنشاء صفحة هبوط جديدة للمنتج' : 'Create New Product Landing Page'); ?></span>
                                </h2>
                                <span style="font-size: 11.5px; color: #64748b; display: block; margin-top: 2px;">
                                    <?php echo $id > 0 ? ('ID: #' . $id . ' | ' . htmlspecialchars($product_to_edit['name_ar'] ?? '')) : ($lang === 'ar' ? 'صفحة هبوط جديدة' : 'New Landing Page'); ?>
                                </span>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <!-- Completion Meter -->
                            <div class="cms-completion-badge" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 14px; border-radius: 20px; display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700;">
                                <span style="color: #64748b;"><?php echo $lang === 'ar' ? 'نسبة الإكتمال:' : 'Completion:'; ?></span>
                                <span id="cms_completion_percent" style="color: #2563eb;">0%</span>
                                <div style="width: 50px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                                    <div id="cms_completion_bar" style="width: 0%; height: 100%; background: #2563eb; transition: width 0.3s ease;"></div>
                                </div>
                            </div>

                            <!-- Draft Indicator Badge -->
                            <span id="draft_status_badge" style="font-size: 11.5px; color: #64748b; font-weight: 600; display: none;"></span>

                            <?php if ($id > 0): ?>
                                <a href="../<?php echo get_product_details_url($product_to_edit, $lang); ?>" target="_blank" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px;">
                                    <i data-lucide="external-link" style="width: 14px; height: 14px;"></i>
                                    <span><?php echo $lang === 'ar' ? 'معاينة حية' : 'Live Preview'; ?></span>
                                </a>
                            <?php endif; ?>

                            <button type="button" class="btn btn-secondary btn-sm" onclick="saveDraftToLocalStorage(true)" style="font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                                <i data-lucide="save" style="width: 14px; height: 14px;"></i>
                                <span><?php echo $lang === 'ar' ? 'حفظ مسودة' : 'Save Draft'; ?></span>
                            </button>

                            <button type="submit" form="productEnterpriseForm" class="btn btn-primary btn-sm" style="font-weight: 700; padding: 8px 18px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; background: #2563eb; border-color: #2563eb;">
                                <i data-lucide="check-circle" style="width: 15px; height: 15px;"></i>
                                <span><?php echo __('admin_save'); ?></span>
                            </button>
                        </div>
                    </div>

                    <!-- Main Container with Sticky Sidebar Section Nav -->
                    <div style="display: flex; flex: 1; overflow: hidden; background: #f8fafc;">
                        
                        <!-- Sticky Section Nav (Sidebar) -->
                        <nav class="cms-section-sidebar" style="width: 220px; flex-shrink: 0; background: #ffffff; border-inline-end: 1px solid #e2e8f0; padding: 16px 10px; overflow-y: auto; display: flex; flex-direction: column; gap: 4px;">
                            <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #94a3b8; padding: 6px 10px; letter-spacing: 0.5px;">
                                <?php echo $lang === 'ar' ? 'أقسام صفحة الهبوط' : 'CMS Sections'; ?>
                            </span>
                            
                            <a href="#sec-identity" class="cms-nav-item active" onclick="jumpToCmsSection(event, 'sec-identity')">
                                <i data-lucide="package" style="width:16px; height:16px;"></i>
                                <span>1. <?php echo $lang === 'ar' ? 'هوية المنتج' : 'Product Identity'; ?></span>
                            </a>
                            <a href="#sec-content" class="cms-nav-item" onclick="jumpToCmsSection(event, 'sec-content')">
                                <i data-lucide="file-text" style="width:16px; height:16px;"></i>
                                <span>2. <?php echo $lang === 'ar' ? 'محتوى الصفحة' : 'Landing Content'; ?></span>
                            </a>
                            <a href="#sec-media" class="cms-nav-item" onclick="jumpToCmsSection(event, 'sec-media')">
                                <i data-lucide="image" style="width:16px; height:16px;"></i>
                                <span>3. <?php echo $lang === 'ar' ? 'الوسائط ومعرض الصور' : 'Media & Gallery'; ?></span>
                            </a>
                            <a href="#sec-seo" class="cms-nav-item" onclick="jumpToCmsSection(event, 'sec-seo')">
                                <i data-lucide="search" style="width:16px; height:16px;"></i>
                                <span>4. <?php echo $lang === 'ar' ? 'تحسين SEO والترميز' : 'SEO Optimization'; ?></span>
                            </a>
                            <a href="#sec-related" class="cms-nav-item" onclick="jumpToCmsSection(event, 'sec-related')">
                                <i data-lucide="link-2" style="width:16px; height:16px;"></i>
                                <span>5. <?php echo $lang === 'ar' ? 'المحتوى المرتبط' : 'Related Content'; ?></span>
                            </a>
                            <a href="#sec-leads" class="cms-nav-item" onclick="jumpToCmsSection(event, 'sec-leads')">
                                <i data-lucide="target" style="width:16px; height:16px;"></i>
                                <span>6. <?php echo $lang === 'ar' ? 'أزرار التحويل CTA' : 'Lead Gen CTAs'; ?></span>
                            </a>
                            <a href="#sec-publishing" class="cms-nav-item" onclick="jumpToCmsSection(event, 'sec-publishing')">
                                <i data-lucide="globe" style="width:16px; height:16px;"></i>
                                <span>7. <?php echo $lang === 'ar' ? 'النشر والحالة' : 'Publishing & Status'; ?></span>
                            </a>
                        </nav>

                        <!-- Main Form & Section Cards -->
                        <form id="productEnterpriseForm" action="products.php<?php echo $action === 'edit' ? '?id='.$id : ''; ?>" method="POST" enctype="multipart/form-data" style="flex: 1; padding: 24px; overflow-y: auto; display: flex; flex-direction: column; gap: 24px;" oninput="updateCmsCompletionProgress()" onchange="saveDraftToLocalStorage(false)" onsubmit="clearDraftLocalStorage()">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="save_product" value="1">

                            <!-- SECTION 1: Product Identity -->
                            <div class="cms-section-card" id="sec-identity">
                                <div class="cms-section-header">
                                    <h3 style="margin:0; font-size:15px; font-weight:700; display:flex; align-items:center; gap:8px;">
                                        <i data-lucide="package" style="color:#2563eb; width:18px;"></i>
                                        <span>1. <?php echo $lang === 'ar' ? 'هوية البيانات الأساسية للمنتج' : 'Product Identity & Core Specs'; ?></span>
                                    </h3>
                                </div>
                                <div class="cms-section-body">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="name_ar"><?php echo __('p_name_ar'); ?> *</label>
                                            <input type="text" name="name_ar" id="name_ar" class="form-control" dir="rtl" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['name_ar']) : ''; ?>" required oninput="updateSeoLivePreview()">
                                        </div>
                                        <div class="form-group">
                                            <label for="name_en"><?php echo __('p_name_en'); ?> *</label>
                                            <input type="text" name="name_en" id="name_en" class="form-control" dir="ltr" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['name_en']) : ''; ?>" required oninput="updateSeoLivePreview(); generateSlug();">
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="slug"><?php echo $lang === 'ar' ? 'معرّف الرابط الإنجليزي (URL Slug)' : 'English URL Slug'; ?></label>
                                            <div style="display: flex; gap: 6px;">
                                                <input type="text" name="slug" id="slug" class="form-control" dir="ltr" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['slug'] ?? '') : ''; ?>" placeholder="heavy-duty-warehouse-shelving">
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="generateSlug()" title="<?php echo $lang === 'ar' ? 'توليد الرابط تلقائياً' : 'Auto Generate'; ?>">
                                                    <i data-lucide="sparkles" style="width:14px;"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="category"><?php echo __('p_category'); ?> *</label>
                                            <select name="category" id="category" class="form-control" required>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?php echo htmlspecialchars($cat['code']); ?>" <?php echo ($product_to_edit && $product_to_edit['category'] === $cat['code']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($lang == 'ar' ? $cat['name_ar'] : $cat['name_en']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="sku"><?php echo $lang === 'ar' ? 'رمز التخزين SKU' : 'SKU Code'; ?></label>
                                            <input type="text" name="sku" id="sku" class="form-control" dir="ltr" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['sku'] ?? '') : ''; ?>" placeholder="WOS-WH-001">
                                        </div>
                                        <div class="form-group">
                                            <label for="brand"><?php echo $lang === 'ar' ? 'العلامة التجارية' : 'Brand Name'; ?></label>
                                            <input type="text" name="brand" id="brand" class="form-control" dir="rtl" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['brand'] ?? 'عالم الرفوف') : 'عالم الرفوف'; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="stock"><?php echo __('p_stock'); ?> *</label>
                                            <input type="number" name="stock" id="stock" class="form-control" dir="ltr" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['stock']) : '10'; ?>" required>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="dimensions_ar"><?php echo $lang === 'ar' ? 'الأبعاد بالعربية' : 'Dimensions in Arabic'; ?></label>
                                            <input type="text" name="dimensions_ar" id="dimensions_ar" class="form-control" dir="rtl" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['dimensions_ar'] ?? '') : ''; ?>" placeholder="مثال: ٢٠٠ × ١٠٠ × ٥٠ سم">
                                        </div>
                                        <div class="form-group">
                                            <label for="dimensions_en"><?php echo $lang === 'ar' ? 'الأبعاد بالإنجليزية' : 'Dimensions in English'; ?></label>
                                            <input type="text" name="dimensions_en" id="dimensions_en" class="form-control" dir="ltr" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['dimensions_en'] ?? '') : ''; ?>" placeholder="e.g. 200x100x50 cm">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="materials_ar"><?php echo __('p_materials_ar'); ?></label>
                                            <input type="text" name="materials_ar" id="materials_ar" class="form-control" dir="rtl" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['materials_ar']) : ''; ?>" placeholder="صُلب مجلفن مقاوم للصدأ">
                                        </div>
                                        <div class="form-group">
                                            <label for="materials_en"><?php echo __('p_materials_en'); ?></label>
                                            <input type="text" name="materials_en" id="materials_en" class="form-control" dir="ltr" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['materials_en']) : ''; ?>" placeholder="Galvanized Heavy Steel">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 2: Landing Page Content -->
                            <div class="cms-section-card" id="sec-content">
                                <div class="cms-section-header">
                                    <h3 style="margin:0; font-size:15px; font-weight:700; display:flex; align-items:center; gap:8px;">
                                        <i data-lucide="file-text" style="color:#2563eb; width:18px;"></i>
                                        <span>2. <?php echo $lang === 'ar' ? 'محتوى صفحة الهبوط التسويقية' : 'Landing Page Content & Selling Points'; ?></span>
                                    </h3>
                                </div>
                                <div class="cms-section-body">
                                    <!-- Short Hero Intro -->
                                    <div class="form-row" style="margin-bottom: 16px;">
                                        <div class="form-group">
                                            <label for="short_intro_ar"><?php echo $lang === 'ar' ? 'مقدمة تسويقية قصيرة (بالعربية)' : 'Short Hero Intro (Arabic)'; ?></label>
                                            <textarea name="short_intro_ar" id="short_intro_ar" class="form-control" dir="rtl" rows="2" placeholder="مقدمة بارزة لجذب الزائر في أعلى الصفحة..."><?php echo $product_to_edit ? htmlspecialchars($product_to_edit['short_intro_ar'] ?? '') : ''; ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="short_intro_en"><?php echo $lang === 'ar' ? 'مقدمة تسويقية قصيرة (بالإنجليزية)' : 'Short Hero Intro (English)'; ?></label>
                                            <textarea name="short_intro_en" id="short_intro_en" class="form-control" dir="ltr" rows="2" placeholder="Engaging headline text for page header..."><?php echo $product_to_edit ? htmlspecialchars($product_to_edit['short_intro_en'] ?? '') : ''; ?></textarea>
                                        </div>
                                    </div>

                                    <!-- Full Description -->
                                    <div class="form-group" style="margin-bottom: 16px;">
                                        <label for="description_ar"><?php echo __('p_desc_ar'); ?></label>
                                        <textarea name="description_ar" id="description_ar" class="form-control" dir="rtl" rows="4" oninput="updateSeoLivePreview()"><?php echo $product_to_edit ? htmlspecialchars($product_to_edit['description_ar']) : ''; ?></textarea>
                                    </div>
                                    <div class="form-group" style="margin-bottom: 16px;">
                                        <label for="description_en"><?php echo __('p_desc_en'); ?></label>
                                        <textarea name="description_en" id="description_en" class="form-control" dir="ltr" rows="4" oninput="updateSeoLivePreview()"><?php echo $product_to_edit ? htmlspecialchars($product_to_edit['description_en']) : ''; ?></textarea>
                                    </div>

                                    <!-- Selling Points Builder -->
                                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                            <label style="font-weight:700; margin:0; font-size:13px;"><?php echo $lang === 'ar' ? 'نقاط البيع والميزات الرئيسية' : 'Main Selling Points & Key Features'; ?></label>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="addSellingPointRow()">
                                                <i data-lucide="plus" style="width:14px;"></i> <?php echo $lang === 'ar' ? 'إضافة ميزة' : 'Add Feature'; ?>
                                            </button>
                                        </div>
                                        <div id="selling-points-container">
                                            <?php foreach ($edit_selling_points as $sp): ?>
                                                <div class="admin-sp-row" style="display:flex; gap:8px; margin-bottom:8px;">
                                                    <input type="text" name="selling_point_ar[]" class="form-control" dir="rtl" value="<?php echo htmlspecialchars($sp['ar'] ?? ''); ?>" placeholder="الميزة بالعربية">
                                                    <input type="text" name="selling_point_en[]" class="form-control" dir="ltr" value="<?php echo htmlspecialchars($sp['en'] ?? ''); ?>" placeholder="Feature in English">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentNode.remove()" style="color:var(--danger);">✕</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Benefits Builder -->
                                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                            <label style="font-weight:700; margin:0; font-size:13px;"><?php echo $lang === 'ar' ? 'فوائد المنتج للعميل' : 'Customer Benefits'; ?></label>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="addBenefitRow()">
                                                <i data-lucide="plus" style="width:14px;"></i> <?php echo $lang === 'ar' ? 'إضافة فائدة' : 'Add Benefit'; ?>
                                            </button>
                                        </div>
                                        <div id="benefits-container">
                                            <?php foreach ($edit_benefits as $b): ?>
                                                <div class="admin-b-row" style="display:flex; gap:8px; margin-bottom:8px;">
                                                    <input type="text" name="benefit_ar[]" class="form-control" dir="rtl" value="<?php echo htmlspecialchars($b['ar'] ?? ''); ?>" placeholder="الفائدة بالعربية">
                                                    <input type="text" name="benefit_en[]" class="form-control" dir="ltr" value="<?php echo htmlspecialchars($b['en'] ?? ''); ?>" placeholder="Benefit in English">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentNode.remove()" style="color:var(--danger);">✕</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Technical Specs Builder -->
                                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                            <label style="font-weight:700; margin:0; font-size:13px;"><?php echo __('technical_specs'); ?></label>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="addAdminTechSpecRow()">
                                                <i data-lucide="plus" style="width:14px;"></i> <?php echo __('add_spec_row'); ?>
                                            </button>
                                        </div>
                                        <div id="admin-specs-container">
                                            <?php foreach ($edit_tech_specs as $sidx => $spec): ?>
                                                <div class="admin-spec-row" style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px; margin-bottom: 10px;">
                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid var(--border-color);">
                                                        <span style="font-weight: 700; font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                                                            <i data-lucide="grip-vertical" style="width: 14px; height: 14px; cursor: grab;"></i>
                                                            <span>#<?php echo $sidx + 1; ?></span>
                                                        </span>
                                                        <div style="display: flex; align-items: center; gap: 6px;">
                                                            <button type="button" class="btn btn-secondary btn-sm" onclick="moveAdminRowUp(this)" title="Move Up" style="padding: 2px 6px; font-size: 11px;">▲</button>
                                                            <button type="button" class="btn btn-secondary btn-sm" onclick="moveAdminRowDown(this)" title="Move Down" style="padding: 2px 6px; font-size: 11px;">▼</button>
                                                            <button type="button" class="btn btn-secondary btn-sm" onclick="removeAdminSpecRow(this)" style="padding: 2px 6px; font-size: 11px; color: var(--danger);">✕</button>
                                                        </div>
                                                    </div>
                                                    <div class="form-row" style="margin-bottom: 6px;">
                                                        <div class="form-group" style="margin-bottom: 0;">
                                                            <input type="text" name="tech_spec_key_ar[]" class="form-control" dir="rtl" value="<?php echo htmlspecialchars($spec['key_ar']); ?>" placeholder="<?php echo __('spec_key_ar'); ?>">
                                                        </div>
                                                        <div class="form-group" style="margin-bottom: 0;">
                                                            <input type="text" name="tech_spec_key_en[]" class="form-control" dir="ltr" value="<?php echo htmlspecialchars($spec['key_en']); ?>" placeholder="<?php echo __('spec_key_en'); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="form-row">
                                                        <div class="form-group" style="margin-bottom: 0;">
                                                            <input type="text" name="tech_spec_val_ar[]" class="form-control" dir="rtl" value="<?php echo htmlspecialchars($spec['value_ar']); ?>" placeholder="<?php echo __('spec_val_ar'); ?>">
                                                        </div>
                                                        <div class="form-group" style="margin-bottom: 0;">
                                                            <input type="text" name="tech_spec_val_en[]" class="form-control" dir="ltr" value="<?php echo htmlspecialchars($spec['value_en']); ?>" placeholder="<?php echo __('spec_val_en'); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Industries Served Builder -->
                                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                            <label style="font-weight:700; margin:0; font-size:13px;"><?php echo $lang === 'ar' ? 'القطاعات والصناعات المستهدفة' : 'Industries Served'; ?></label>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="addIndustryRow()">
                                                <i data-lucide="plus" style="width:14px;"></i> <?php echo $lang === 'ar' ? 'إضافة قطاع' : 'Add Industry'; ?>
                                            </button>
                                        </div>
                                        <div id="industries-container">
                                            <?php foreach ($edit_industries as $ind): ?>
                                                <div class="admin-ind-row" style="display:flex; gap:8px; margin-bottom:8px;">
                                                    <input type="text" name="industry_ar[]" class="form-control" dir="rtl" value="<?php echo htmlspecialchars($ind['ar'] ?? ''); ?>" placeholder="القطاع (مثال: المستودعات والخدمات اللوجستية)">
                                                    <input type="text" name="industry_en[]" class="form-control" dir="ltr" value="<?php echo htmlspecialchars($ind['en'] ?? ''); ?>" placeholder="Industry (e.g. Logistics & Warehousing)">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentNode.remove()" style="color:var(--danger);">✕</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Downloadable PDF Catalog -->
                                    <div class="form-group">
                                        <label for="catalog_pdf_url"><?php echo $lang === 'ar' ? 'رابط ملف الكتالوج PDF للتحميل' : 'Downloadable PDF Catalog URL'; ?></label>
                                        <input type="text" name="catalog_pdf_url" id="catalog_pdf_url" class="form-control" dir="ltr" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['catalog_pdf_url'] ?? '') : ''; ?>" placeholder="https://worldofshelves.com/uploads/catalogs/product-spec.pdf">
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 3: Media & Assets -->
                            <div class="cms-section-card" id="sec-media">
                                <div class="cms-section-header">
                                    <h3 style="margin:0; font-size:15px; font-weight:700; display:flex; align-items:center; gap:8px;">
                                        <i data-lucide="image" style="color:#2563eb; width:18px;"></i>
                                        <span>3. <?php echo $lang === 'ar' ? 'الوسائط والمعرض وصورة المشاركة' : 'Media, Gallery & OpenGraph Assets'; ?></span>
                                    </h3>
                                </div>
                                <div class="cms-section-body">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="image_file"><?php echo __('p_image'); ?> <?php echo $action === 'edit' ? '' : '*'; ?></label>
                                            <input type="file" name="image_file" id="image_file" class="form-control" accept="image/jpeg,image/png,image/webp" style="padding: 5px;" <?php echo $action === 'edit' ? '' : 'required'; ?> onchange="previewLocalImage(this, 'main_image_preview')">
                                            <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">
                                                <?php echo __('allowed_image_formats'); ?>
                                            </span>
                                        </div>
                                        <div class="form-group" style="background: #f8fafc; border: 1px solid var(--border-color); padding: 12px; border-radius: var(--radius-sm);">
                                            <label style="font-weight: 700; font-size: 12px; color: var(--text-secondary); display: block; margin-bottom: 6px;">
                                                <?php echo get_current_lang() === 'ar' ? 'معاينة صورة المنتج الرئيسية' : 'Main Product Image Preview'; ?>
                                            </label>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <img id="main_image_preview" src="<?php echo ($product_to_edit && !empty($product_to_edit['image_url'])) ? ('../' . htmlspecialchars($product_to_edit['image_url'])) : 'assets/images/shelf1.png'; ?>" alt="Main Preview" style="width: 70px; height: 70px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-color); <?php echo ($product_to_edit && !empty($product_to_edit['image_url'])) ? '' : 'display:none;'; ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Product Gallery Multi-upload (Max 4 limit) -->
                                    <div style="margin-top: 16px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                            <label style="font-weight: 700; margin: 0; font-size: 13px;"><?php echo __('admin_add_gallery_image'); ?></label>
                                            <?php 
                                            $non_primary_count = 0;
                                            if (!empty($edit_gallery_images)) {
                                                foreach ($edit_gallery_images as $g) {
                                                    if (empty($g['is_primary'])) $non_primary_count++;
                                                }
                                            }
                                            ?>
                                            <span class="badge" style="background: #e2e8f0; color: #475569; font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: 700;">
                                                <?php echo get_current_lang() === 'ar' ? 'الصور الحالية: ' : 'Current: '; ?><?php echo $non_primary_count; ?> / 4
                                            </span>
                                        </div>
                                        <input type="file" name="gallery_files[]" id="gallery_files_input" multiple class="form-control" accept="image/jpeg,image/png,image/webp" style="padding: 6px;" onchange="validateMaxGalleryImages(this, <?php echo $non_primary_count; ?>)">
                                        <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">
                                            <?php echo get_current_lang() === 'ar' ? 'يمكنك تحديد حتى 4 صور إضافية كحد أقصى للمعرض' : 'You can upload up to 4 additional gallery images maximum'; ?>
                                        </span>

                                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; margin-top: 14px;">
                                            <?php foreach ($edit_gallery_images as $gimg): ?>
                                                <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 8px; text-align: center;">
                                                    <img src="../<?php echo htmlspecialchars($gimg['image_path']); ?>" alt="Gallery image" style="width: 100%; height: 90px; object-fit: cover; border-radius: var(--radius-sm); margin-bottom: 6px;">
                                                    <?php if (!empty($gimg['is_primary'])): ?>
                                                        <span class="badge" style="background: var(--accent-primary); color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 10px; display: inline-block;"><?php echo __('primary_image_badge'); ?></span>
                                                    <?php else: ?>
                                                        <button type="submit" name="set_primary_img_id" value="<?php echo $gimg['id']; ?>" class="btn btn-secondary btn-sm" style="font-size: 10px; padding: 2px 6px; width: 100%; margin-bottom: 4px;">
                                                            <?php echo __('set_as_primary'); ?>
                                                        </button>
                                                        <label style="display: flex; align-items: center; justify-content: center; gap: 4px; font-size: 11px; color: var(--danger); cursor: pointer;">
                                                            <input type="checkbox" name="delete_gallery_ids[]" value="<?php echo $gimg['id']; ?>">
                                                            <span><?php echo get_current_lang() === 'ar' ? 'حذف' : 'Delete'; ?></span>
                                                        </label>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 4: SEO Optimization -->
                            <div class="cms-section-card" id="sec-seo">
                                <div class="cms-section-header">
                                    <h3 style="margin:0; font-size:15px; font-weight:700; display:flex; align-items:center; gap:8px;">
                                        <i data-lucide="search" style="color:#2563eb; width:18px;"></i>
                                        <span>4. <?php echo $lang === 'ar' ? 'تحسين محركات البحث SEO والترميز الـ Schema' : 'SEO Optimization & SERP Preview'; ?></span>
                                    </h3>
                                </div>
                                <div class="cms-section-body">
                                    <!-- Google SERP Snippet Preview -->
                                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                        <h4 style="margin-top:0; margin-bottom:10px; font-size:13px; color:#475569; display:flex; align-items:center; gap:6px;">
                                            <i data-lucide="globe" style="width:14px; color:#2563eb;"></i>
                                            <span><?php echo $lang === 'ar' ? 'معاينة النتيجة في جوجل (Google SERP Preview)' : 'Google Search Result Preview'; ?></span>
                                        </h4>
                                        <div style="font-family: Arial, sans-serif; background: #fff; padding: 10px; border-radius: 6px;">
                                            <div id="serp_preview_url" style="font-size: 12px; color: #202124; margin-bottom: 4px; word-break: break-all;">https://worldofshelves.com/product-details.php?id=<?php echo $id > 0 ? $id : 'NEW'; ?></div>
                                            <div id="serp_preview_title" style="font-size: 18px; color: #1a0dab; line-height: 1.3; font-weight: 400; margin-bottom: 4px; cursor: pointer;">Product Name - World of Shelves</div>
                                            <div id="serp_preview_desc" style="font-size: 13px; color: #4d5156; line-height: 1.58;">Snippet description preview will appear here dynamically as you type...</div>
                                        </div>
                                    </div>

                                    <!-- Focus Keyword -->
                                    <div class="form-row" style="margin-bottom: 16px;">
                                        <div class="form-group">
                                            <label for="focus_keyword_ar"><?php echo $lang === 'ar' ? 'الكلمة المفتاحية المستهدفة (بالعربية)' : 'Focus Keyword (Arabic)'; ?></label>
                                            <input type="text" name="focus_keyword_ar" id="focus_keyword_ar" class="form-control" dir="rtl" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['focus_keyword_ar'] ?? '') : ''; ?>" placeholder="مثال: رفوف مستودعات حديدية">
                                        </div>
                                        <div class="form-group">
                                            <label for="focus_keyword_en"><?php echo $lang === 'ar' ? 'الكلمة المفتاحية المستهدفة (بالإنجليزية)' : 'Focus Keyword (English)'; ?></label>
                                            <input type="text" name="focus_keyword_en" id="focus_keyword_en" class="form-control" dir="ltr" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['focus_keyword_en'] ?? '') : ''; ?>" placeholder="e.g. industrial warehouse shelving">
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="seo_title_ar" style="display:flex; justify-content:space-between;">
                                                <span><?php echo __('seo_title_ar'); ?></span>
                                                <span id="cnt_seo_title_ar" class="badge" style="font-size:10px;">0/60</span>
                                            </label>
                                            <input type="text" name="seo_title_ar" id="seo_title_ar" class="form-control" dir="rtl" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['seo_title_ar'] ?? '') : ''; ?>" oninput="updateSeoLivePreview()">
                                        </div>
                                        <div class="form-group">
                                            <label for="seo_title_en" style="display:flex; justify-content:space-between;">
                                                <span><?php echo __('seo_title_en'); ?></span>
                                                <span id="cnt_seo_title_en" class="badge" style="font-size:10px;">0/60</span>
                                            </label>
                                            <input type="text" name="seo_title_en" id="seo_title_en" class="form-control" dir="ltr" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['seo_title_en'] ?? '') : ''; ?>" oninput="updateSeoLivePreview()">
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="meta_description_ar" style="display:flex; justify-content:space-between;">
                                                <span><?php echo __('meta_desc_ar'); ?></span>
                                                <span id="cnt_meta_description_ar" class="badge" style="font-size:10px;">0/160</span>
                                            </label>
                                            <textarea name="meta_description_ar" id="meta_description_ar" class="form-control" dir="rtl" rows="2" oninput="updateSeoLivePreview()"><?php echo $product_to_edit ? htmlspecialchars($product_to_edit['meta_description_ar'] ?? '') : ''; ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="meta_description_en" style="display:flex; justify-content:space-between;">
                                                <span><?php echo __('meta_desc_en'); ?></span>
                                                <span id="cnt_meta_description_en" class="badge" style="font-size:10px;">0/160</span>
                                            </label>
                                            <textarea name="meta_description_en" id="meta_description_en" class="form-control" dir="ltr" rows="2" oninput="updateSeoLivePreview()"><?php echo $product_to_edit ? htmlspecialchars($product_to_edit['meta_description_en'] ?? '') : ''; ?></textarea>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="alt_text_ar"><?php echo __('alt_text_ar'); ?></label>
                                            <input type="text" name="alt_text_ar" id="alt_text_ar" class="form-control" dir="rtl" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['alt_text_ar'] ?? '') : ''; ?>" oninput="updateSeoLivePreview()">
                                        </div>
                                        <div class="form-group">
                                            <label for="alt_text_en"><?php echo __('alt_text_en'); ?></label>
                                            <input type="text" name="alt_text_en" id="alt_text_en" class="form-control" dir="ltr" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['alt_text_en'] ?? '') : ''; ?>" oninput="updateSeoLivePreview()">
                                        </div>
                                    </div>

                                    <!-- Schema Identifiers -->
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="mpn">MPN</label>
                                            <input type="text" name="mpn" id="mpn" class="form-control" dir="ltr" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['mpn'] ?? '') : ''; ?>" placeholder="MPN-9988">
                                        </div>
                                        <div class="form-group">
                                            <label for="gtin">GTIN / Barcode</label>
                                            <input type="text" name="gtin" id="gtin" class="form-control" dir="ltr" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['gtin'] ?? '') : ''; ?>" placeholder="1234567890123">
                                        </div>
                                        <div class="form-group">
                                            <label for="canonical_url">Canonical URL</label>
                                            <input type="url" name="canonical_url" id="canonical_url" class="form-control" dir="ltr" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['canonical_url'] ?? '') : ''; ?>" placeholder="https://worldofshelves.com/product-details?id=1">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 5: Related Content -->
                            <div class="cms-section-card" id="sec-related">
                                <div class="cms-section-header">
                                    <h3 style="margin:0; font-size:15px; font-weight:700; display:flex; align-items:center; gap:8px;">
                                        <i data-lucide="link-2" style="color:#2563eb; width:18px;"></i>
                                        <span>5. <?php echo $lang === 'ar' ? 'المحتوى المرتبط والمنتجات والمشاريع والخدمات' : 'Related Products, Projects & Services'; ?></span>
                                    </h3>
                                </div>
                                <div class="cms-section-body">
                                    <!-- Related Products Picker -->
                                    <div style="margin-bottom: 20px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px;">
                                        <label style="font-weight:700; display:block; margin-bottom:8px; font-size:13px;"><?php echo __('related_products'); ?></label>
                                        <div style="display:flex; gap:8px; margin-bottom:12px;">
                                            <select id="related-product-picker" class="form-control">
                                                <option value=""><?php echo __('select_related_product'); ?></option>
                                                <?php foreach ($all_active_products as $ap): ?>
                                                    <option value="<?php echo $ap['id']; ?>" data-name-ar="<?php echo htmlspecialchars($ap['name_ar']); ?>" data-name-en="<?php echo htmlspecialchars($ap['name_en']); ?>" data-category="<?php echo htmlspecialchars($ap['category']); ?>" data-img="<?php echo htmlspecialchars($ap['image_url']); ?>">
                                                        <?php echo htmlspecialchars($lang == 'ar' ? $ap['name_ar'] : $ap['name_en']); ?> (ID: #<?php echo $ap['id']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="addSelectedRelatedProduct()">
                                                <i data-lucide="plus" style="width:14px;"></i> <?php echo __('add_related_product'); ?>
                                            </button>
                                        </div>
                                        <div id="admin-related-container">
                                            <?php foreach ($edit_related_ids as $rid): 
                                                $match_p = array_filter($all_active_products, function($p) use ($rid) { return (int)$p['id'] === (int)$rid; });
                                                $match_p = reset($match_p);
                                                if ($match_p):
                                            ?>
                                                <div class="admin-related-row" data-id="<?php echo $match_p['id']; ?>" style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-primary); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); margin-bottom: 8px;">
                                                    <input type="hidden" name="related_product_ids[]" value="<?php echo $match_p['id']; ?>">
                                                    <div style="display: flex; align-items: center; gap: 10px;">
                                                        <img src="../<?php echo htmlspecialchars($match_p['image_url']); ?>" style="width: 36px; height: 36px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border-color);">
                                                        <div>
                                                            <strong style="font-size: 13px; display: block;"><?php echo htmlspecialchars($lang === 'ar' ? $match_p['name_ar'] : $match_p['name_en']); ?></strong>
                                                            <span style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($match_p['category']); ?></span>
                                                        </div>
                                                    </div>
                                                    <div style="display: flex; align-items: center; gap: 6px;">
                                                        <button type="button" class="btn btn-secondary btn-sm" onclick="moveAdminRowUp(this)" title="Move Up" style="padding: 2px 6px; font-size: 11px;">▲</button>
                                                        <button type="button" class="btn btn-secondary btn-sm" onclick="moveAdminRowDown(this)" title="Move Down" style="padding: 2px 6px; font-size: 11px;">▼</button>
                                                        <button type="button" class="btn btn-secondary btn-sm" onclick="removeAdminRelatedRow(this)" style="padding: 2px 6px; font-size: 11px; color: var(--danger);">✕</button>
                                                    </div>
                                                </div>
                                            <?php endif; endforeach; ?>
                                        </div>
                                    </div>

                                    <?php if (false): ?>
                                    <!-- Related Projects Selector -->
                                    <div style="margin-bottom: 20px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px;">
                                        <label style="font-weight:700; display:block; margin-bottom:8px; font-size:13px;"><?php echo $lang === 'ar' ? 'المشاريع ذات الصلة (من معرض الأعمال)' : 'Related Portfolio Projects'; ?></label>
                                        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
                                            <?php foreach ($all_active_projects as $prj): 
                                                $checked = in_array((int)$prj['id'], $edit_related_project_ids, true);
                                            ?>
                                                <label style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #e2e8f0; padding:8px 10px; border-radius:6px; font-size:12px; cursor:pointer;">
                                                    <input type="checkbox" name="related_project_ids[]" value="<?php echo $prj['id']; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                                    <span><?php echo htmlspecialchars($lang === 'ar' ? $prj['title_ar'] : $prj['title_en']); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Related Services Selector -->
                                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px;">
                                        <label style="font-weight:700; display:block; margin-bottom:8px; font-size:13px;"><?php echo $lang === 'ar' ? 'الخدمات ذات الصلة (التركيب، الصيانة، الفك والنقل)' : 'Related Storage Services'; ?></label>
                                        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
                                            <?php foreach ($all_active_services as $srv): 
                                                $checked = in_array((int)$srv['id'], $edit_related_service_ids, true);
                                            ?>
                                                <label style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #e2e8f0; padding:8px 10px; border-radius:6px; font-size:12px; cursor:pointer;">
                                                    <input type="checkbox" name="related_service_ids[]" value="<?php echo $srv['id']; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                                    <span><?php echo htmlspecialchars($lang === 'ar' ? $srv['title_ar'] : $srv['title_en']); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 6: Lead Generation CTAs -->
                            <div class="cms-section-card" id="sec-leads">
                                <div class="cms-section-header">
                                    <h3 style="margin:0; font-size:15px; font-weight:700; display:flex; align-items:center; gap:8px;">
                                        <i data-lucide="target" style="color:#2563eb; width:18px;"></i>
                                        <span>6. <?php echo $lang === 'ar' ? 'إعدادات أزرار التحويل وتوليد العملاء (CTA Buttons)' : 'Lead Generation & CTA Configuration'; ?></span>
                                    </h3>
                                </div>
                                <div class="cms-section-body">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px;">
                                        <label style="display:flex; align-items:center; gap:10px; background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer;">
                                            <input type="checkbox" name="cta_request_quote" value="1" <?php echo (!isset($edit_cta_settings['request_quote']) || $edit_cta_settings['request_quote']) ? 'checked' : ''; ?>>
                                            <span>🟢 <?php echo $lang === 'ar' ? 'زر طلب عرض سعر' : 'Request Quote Button'; ?></span>
                                        </label>

                                        <label style="display:flex; align-items:center; gap:10px; background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer;">
                                            <input type="checkbox" name="cta_whatsapp" value="1" <?php echo (!isset($edit_cta_settings['whatsapp']) || $edit_cta_settings['whatsapp']) ? 'checked' : ''; ?>>
                                            <span>💬 <?php echo $lang === 'ar' ? 'زر واتساب المباشر' : 'WhatsApp Button'; ?></span>
                                        </label>

                                        <label style="display:flex; align-items:center; gap:10px; background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer;">
                                            <input type="checkbox" name="cta_call_now" value="1" <?php echo (!isset($edit_cta_settings['call_now']) || $edit_cta_settings['call_now']) ? 'checked' : ''; ?>>
                                            <span>📞 <?php echo $lang === 'ar' ? 'زر الاتصال المباشر' : 'Call Now Button'; ?></span>
                                        </label>

                                        <label style="display:flex; align-items:center; gap:10px; background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer;">
                                            <input type="checkbox" name="cta_download_catalog" value="1" <?php echo (!isset($edit_cta_settings['download_catalog']) || $edit_cta_settings['download_catalog']) ? 'checked' : ''; ?>>
                                            <span>📄 <?php echo $lang === 'ar' ? 'زر تحميل الكتالوج' : 'Download Catalog'; ?></span>
                                        </label>

                                        <label style="display:flex; align-items:center; gap:10px; background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer;">
                                            <input type="checkbox" name="cta_site_visit" value="1" <?php echo (!isset($edit_cta_settings['site_visit']) || $edit_cta_settings['site_visit']) ? 'checked' : ''; ?>>
                                            <span>🏗️ <?php echo $lang === 'ar' ? 'زر طلب معاينة الموقع' : 'Request Site Visit'; ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 7: Publishing & Status -->
                            <div class="cms-section-card" id="sec-publishing">
                                <div class="cms-section-header">
                                    <h3 style="margin:0; font-size:15px; font-weight:700; display:flex; align-items:center; gap:8px;">
                                        <i data-lucide="globe" style="color:#2563eb; width:18px;"></i>
                                        <span>7. <?php echo $lang === 'ar' ? 'إعدادات النشر وخيارات الترويج' : 'Publishing & Homepage Visibility'; ?></span>
                                    </h3>
                                </div>
                                <div class="cms-section-body">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 16px;">
                                        <label style="display: flex; align-items: center; gap: 8px; font-weight:600; cursor:pointer; background:#fafafa; padding:10px; border-radius:6px; border:1px solid #eee;">
                                            <input type="checkbox" name="is_active" value="1" <?php echo (!$product_to_edit || !isset($product_to_edit['is_active']) || $product_to_edit['is_active']) ? 'checked' : ''; ?>>
                                            <span><?php echo __('active'); ?></span>
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 8px; font-weight:600; cursor:pointer; background:#fafafa; padding:10px; border-radius:6px; border:1px solid #eee;">
                                            <input type="checkbox" name="is_featured" value="1" <?php echo ($product_to_edit && !empty($product_to_edit['is_featured'])) ? 'checked' : ''; ?>>
                                            <span><?php echo __('show_on_homepage'); ?></span>
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 8px; font-weight:600; cursor:pointer; background:#fafafa; padding:10px; border-radius:6px; border:1px solid #eee;">
                                            <input type="checkbox" name="is_most_requested" value="1" <?php echo ($product_to_edit && !empty($product_to_edit['is_most_requested'])) ? 'checked' : ''; ?>>
                                            <span><?php echo __('most_requested'); ?></span>
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 8px; font-weight:600; cursor:pointer; background:#fafafa; padding:10px; border-radius:6px; border:1px solid #eee;">
                                            <input type="checkbox" name="is_new" value="1" <?php echo ($product_to_edit && !empty($product_to_edit['is_new'])) ? 'checked' : ''; ?>>
                                            <span><?php echo __('new_product'); ?></span>
                                        </label>
                                    </div>
                                    <div class="form-row" style="margin-top: 16px;">
                                        <div class="form-group" style="max-width: 250px;">
                                            <label for="sort_order"><?php echo $lang === 'ar' ? 'ترتيب العرض' : 'Display Order'; ?></label>
                                            <input type="number" name="sort_order" id="sort_order" class="form-control" min="0" step="1" value="<?php echo $product_to_edit ? htmlspecialchars($product_to_edit['sort_order']) : '0'; ?>">
                                        </div>
                                    </div>
                                </div>
                        </div>
                    </div>
                </div>
                        
                        <div class="admin-modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; background: var(--bg-primary);">
                            <a href="products.php" class="btn btn-secondary"><?php echo __('admin_cancel'); ?></a>
                            <button type="submit" class="btn btn-primary"><?php echo __('admin_save'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Client-side Media Mapping JSON & Dynamic SEO Interactive Scripts -->
            <script src="../assets/js/admin-product-gallery.js"></script>
            <script>
            const mediaPathMap = <?php echo json_encode($media_path_map, JSON_UNESCAPED_SLASHES); ?>;

            function switchProductTab(btn, tabId) {
                document.querySelectorAll('#productModal .admin-tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('#productModal .admin-tab-pane').forEach(p => p.style.display = 'none');
                btn.classList.add('active');
                const target = document.getElementById(tabId);
                if (target) target.style.display = 'block';
            }

            function updateCharCounter(id, max) {
                const el = document.getElementById(id);
                const badge = document.getElementById('cnt_' + id);
                if (!el || !badge) return;
                const len = el.value.trim().length;
                badge.innerText = len + '/' + max;
                if (len > max) {
                    badge.style.backgroundColor = '#fee2e2';
                    badge.style.color = '#ef4444';
                } else if (len > 0) {
                    badge.style.backgroundColor = '#dcfce7';
                    badge.style.color = '#15803d';
                } else {
                    badge.style.backgroundColor = '#f1f5f9';
                    badge.style.color = '#64748b';
                }
            }

            function previewLocalImage(input, previewImgId) {
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewEl = document.getElementById(previewImgId);
                        if (previewEl) {
                            previewEl.src = e.target.result;
                            previewEl.style.display = 'block';
                        }
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            }

            function previewMediaSelect(select, previewImgId, mediaMap) {
                const val = select.value;
                const previewEl = document.getElementById(previewImgId);
                if (!previewEl) return;
                if (val && mediaMap[val]) {
                    previewEl.src = '../' + mediaMap[val];
                    previewEl.style.display = 'block';
                }
            }

            function updateSeoLivePreview() {
                const titleAr = document.getElementById('seo_title_ar')?.value.trim() || '';
                const titleEn = document.getElementById('seo_title_en')?.value.trim() || '';
                const descAr = document.getElementById('meta_description_ar')?.value.trim() || '';
                const descEn = document.getElementById('meta_description_en')?.value.trim() || '';
                const nameAr = document.getElementById('name_ar')?.value.trim() || '';
                const nameEn = document.getElementById('name_en')?.value.trim() || '';
                const rawDescAr = document.getElementById('description_ar')?.value.trim() || '';
                const rawDescEn = document.getElementById('description_en')?.value.trim() || '';
                const canonical = document.getElementById('canonical_url')?.value.trim() || '';

                updateCharCounter('seo_title_ar', 60);
                updateCharCounter('seo_title_en', 60);
                updateCharCounter('meta_description_ar', 160);
                updateCharCounter('meta_description_en', 160);

                const isAr = '<?php echo get_current_lang(); ?>' === 'ar';
                const activeTitle = (isAr ? (titleAr || nameAr) : (titleEn || nameEn)) || 'Product Name';
                const siteBrand = isAr ? 'عالم الرفوف' : 'World of Shelves';
                const serpTitle = activeTitle + ' - ' + siteBrand;

                let activeDesc = isAr ? (descAr || rawDescAr) : (descEn || rawDescEn);
                if (!activeDesc) activeDesc = isAr ? 'سيتم توليد وصف البحث تلقائياً من المحتوى...' : 'A search engine snippet will be generated automatically...';
                if (activeDesc.length > 155) activeDesc = activeDesc.substring(0, 155) + '...';

                let currentSlug = document.getElementById('slug') ? document.getElementById('slug').value.trim() : '';
                if (!currentSlug) {
                    currentSlug = '<?php echo $product_to_edit && !empty($product_to_edit['slug']) ? htmlspecialchars($product_to_edit['slug']) : "NEW"; ?>';
                }
                const defaultUrl = 'https://worldofshelves.com/products/' + currentSlug;
                const serpUrl = canonical || defaultUrl;

                if (document.getElementById('serp_preview_title')) document.getElementById('serp_preview_title').innerText = serpTitle;
                if (document.getElementById('serp_preview_url')) document.getElementById('serp_preview_url').innerText = serpUrl;
                if (document.getElementById('serp_preview_desc')) document.getElementById('serp_preview_desc').innerText = activeDesc;

                let score = 0;
                const checks = [];

                const activeSeoTitle = isAr ? titleAr : titleEn;
                if (activeSeoTitle) {
                    if (activeSeoTitle.length >= 30 && activeSeoTitle.length <= 60) {
                        score += 30;
                        checks.push({ ok: true, msg: isAr ? 'عنوان SEO ممتاز (30-60 حرف)' : 'Optimal SEO Title length (30-60 chars)' });
                    } else {
                        score += 15;
                        checks.push({ ok: false, msg: isAr ? 'طول عنوان SEO غير مثالي (يفضل 30-60 حرف)' : 'SEO Title length non-optimal (recommended 30-60 chars)' });
                    }
                } else {
                    checks.push({ ok: false, msg: isAr ? 'عنوان SEO غير محدد (يتم استخدام الاسم افتراضياً)' : 'SEO Title not set (using fallback product name)' });
                }

                const activeMetaDesc = isAr ? descAr : descEn;
                if (activeMetaDesc) {
                    if (activeMetaDesc.length >= 70 && activeMetaDesc.length <= 160) {
                        score += 30;
                        checks.push({ ok: true, msg: isAr ? 'وصف Meta ممتاز (70-160 حرف)' : 'Optimal Meta Description length (70-160 chars)' });
                    } else {
                        score += 15;
                        checks.push({ ok: false, msg: isAr ? 'طول وصف Meta غير مثالي (يفضل 70-160 حرف)' : 'Meta Description length non-optimal (recommended 70-160 chars)' });
                    }
                } else {
                    checks.push({ ok: false, msg: isAr ? 'وصف Meta غير محدد (يتم اقتصاص الوصف العام افتراضياً)' : 'Meta Description not set (auto-truncating main description)' });
                }

                const altAr = document.getElementById('alt_text_ar')?.value.trim() || '';
                const altEn = document.getElementById('alt_text_en')?.value.trim() || '';
                if (altAr || altEn) {
                    score += 20;
                    checks.push({ ok: true, msg: isAr ? 'النص البديل Alt Text مضاف' : 'Image Alt Text configured' });
                } else {
                    checks.push({ ok: false, msg: isAr ? 'لم يتم إضافة نص بديل Alt Text (يتم استخدام الاسم افتراضياً)' : 'Image Alt Text not set (using product name fallback)' });
                }

                const ogSelect = document.getElementById('og_media_id')?.value || '';
                const ogFile = document.getElementById('og_image_file')?.files?.length || 0;
                if (ogSelect || ogFile || '<?php echo !empty($product_to_edit['og_media_id']); ?>') {
                    score += 20;
                    checks.push({ ok: true, msg: isAr ? 'صورة OpenGraph مخصصة مضافة' : 'Custom OpenGraph image selected' });
                } else {
                    checks.push({ ok: false, msg: isAr ? 'لم تحدد صورة OG مخصصة (يتم استخدام صورة المنتج الرئيسية افتراضياً)' : 'No custom OG image selected (using main product image)' });
                }

                const scoreEl = document.getElementById('seo_score_value');
                const fillEl = document.getElementById('seo_score_fill');
                const listEl = document.getElementById('seo_checklist');

                if (scoreEl) scoreEl.innerText = score + ' / 100';
                if (fillEl) {
                    fillEl.style.width = score + '%';
                    fillEl.style.backgroundColor = score >= 80 ? 'var(--success)' : (score >= 50 ? 'var(--warning)' : 'var(--danger)');
                }
                if (listEl) {
                    listEl.innerHTML = checks.map(c => `
                        <div style="display:flex; align-items:center; gap:6px; font-size:12px; color:${c.ok ? 'var(--success)' : '#d97706'}; margin-bottom:4px;">
                            <i data-lucide="${c.ok ? 'check-circle' : 'alert-circle'}" style="width:14px; height:14px; flex-shrink:0;"></i>
                            <span>${c.msg}</span>
                        </div>
                    `).join('');
                    if (window.lucide) lucide.createIcons();
                }
            }

            let slugManuallyEdited = false;
            const isEditMode = <?php echo ($action === 'edit') ? 'true' : 'false'; ?>;

            document.addEventListener('DOMContentLoaded', () => {
                const slugInput = document.getElementById('slug');
                if (slugInput) {
                    slugInput.addEventListener('input', () => {
                        slugManuallyEdited = true;
                    });
                }
            });

            function generateSlug() {
                const slugInput = document.getElementById('slug');
                if (!slugInput) return;
                
                // If existing product, do not overwrite unless empty
                if (isEditMode && slugInput.value.trim() !== '') {
                    return;
                }
                
                // If manually edited, do not auto-generate
                if (slugManuallyEdited) {
                    return;
                }

                const nameEnInput = document.getElementById('name_en');
                if (!nameEnInput) return;
                
                const val = nameEnInput.value.trim().toLowerCase();
                const generated = val.replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
                slugInput.value = generated;
            }

            function addSellingPointRow(ar = '', en = '') {
                const container = document.getElementById('selling-points-container');
                if (!container) return;
                const div = document.createElement('div');
                div.className = 'admin-sp-row';
                div.style.cssText = 'display:flex; gap:8px; margin-bottom:8px;';
                div.innerHTML = `
                    <input type="text" name="selling_point_ar[]" class="form-control" dir="rtl" value="${ar}" placeholder="الميزة بالعربية">
                    <input type="text" name="selling_point_en[]" class="form-control" dir="ltr" value="${en}" placeholder="Feature in English">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentNode.remove()" style="color:var(--danger);">✕</button>
                `;
                container.appendChild(div);
            }

            function addBenefitRow(ar = '', en = '') {
                const container = document.getElementById('benefits-container');
                if (!container) return;
                const div = document.createElement('div');
                div.className = 'admin-b-row';
                div.style.cssText = 'display:flex; gap:8px; margin-bottom:8px;';
                div.innerHTML = `
                    <input type="text" name="benefit_ar[]" class="form-control" dir="rtl" value="${ar}" placeholder="الفائدة بالعربية">
                    <input type="text" name="benefit_en[]" class="form-control" dir="ltr" value="${en}" placeholder="Benefit in English">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentNode.remove()" style="color:var(--danger);">✕</button>
                `;
                container.appendChild(div);
            }

            function addIndustryRow(ar = '', en = '') {
                const container = document.getElementById('industries-container');
                if (!container) return;
                const div = document.createElement('div');
                div.className = 'admin-ind-row';
                div.style.cssText = 'display:flex; gap:8px; margin-bottom:8px;';
                div.innerHTML = `
                    <input type="text" name="industry_ar[]" class="form-control" dir="rtl" value="${ar}" placeholder="القطاع (مثال: المستودعات والخدمات اللوجستية)">
                    <input type="text" name="industry_en[]" class="form-control" dir="ltr" value="${en}" placeholder="Industry (e.g. Logistics & Warehousing)">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentNode.remove()" style="color:var(--danger);">✕</button>
                `;
                container.appendChild(div);
            }

            function jumpToCmsSection(e, secId) {
                if (e) e.preventDefault();
                const sec = document.getElementById(secId);
                if (sec) {
                    sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    document.querySelectorAll('.cms-nav-item').forEach(el => el.classList.remove('active'));
                    if (e && e.currentTarget) e.currentTarget.classList.add('active');
                }
            }

            function updateCmsCompletionProgress() {
                const form = document.getElementById('productEnterpriseForm');
                if (!form) return;
                const fields = ['name_ar', 'name_en', 'description_ar', 'short_intro_ar', 'image_file', 'sku', 'seo_title_ar', 'meta_description_ar'];
                let filled = 0;
                fields.forEach(f => {
                    const el = document.getElementById(f);
                    if (el && el.value.trim() !== '') filled++;
                });
                const percent = Math.min(100, Math.round((filled / fields.length) * 100));
                const bar = document.getElementById('cms_completion_bar');
                const text = document.getElementById('cms_completion_percent');
                if (bar) bar.style.width = percent + '%';
                if (text) text.innerText = percent + '%';
            }

            function getDraftStorageKey() {
                const pid = '<?php echo $id; ?>';
                return 'product_cms_draft_' + (pid > 0 ? pid : 'new');
            }

            function saveDraftToLocalStorage(isManual = false) {
                const key = getDraftStorageKey();
                const nameAr = document.getElementById('name_ar')?.value || '';
                const nameEn = document.getElementById('name_en')?.value || '';
                const descAr = document.getElementById('description_ar')?.value || '';
                const shortAr = document.getElementById('short_intro_ar')?.value || '';

                if (!nameAr && !nameEn && !descAr) return;

                const payload = {
                    timestamp: new Date().getTime(),
                    name_ar: nameAr,
                    name_en: nameEn,
                    description_ar: descAr,
                    short_intro_ar: shortAr
                };

                try {
                    localStorage.setItem(key, JSON.stringify(payload));
                    const badge = document.getElementById('draft_status_badge');
                    if (badge) {
                        badge.style.display = 'inline';
                        badge.innerText = '<?php echo $lang === 'ar' ? "تم حفظ المسودة محلياً" : "Draft saved locally"; ?>';
                    }
                    if (isManual) {
                        alert('<?php echo $lang === "ar" ? "تم حفظ مسودة المنتج بنجاح." : "Draft saved successfully."; ?>');
                    }
                } catch(e) {}
            }

            function clearDraftLocalStorage() {
                try {
                    localStorage.removeItem(getDraftStorageKey());
                } catch(e) {}
            }

            function updateSeoLivePreview() {
                const titleAr = document.getElementById('seo_title_ar')?.value.trim() || '';
                const titleEn = document.getElementById('seo_title_en')?.value.trim() || '';
                const descAr = document.getElementById('meta_description_ar')?.value.trim() || '';
                const descEn = document.getElementById('meta_description_en')?.value.trim() || '';
                const nameAr = document.getElementById('name_ar')?.value.trim() || '';
                const nameEn = document.getElementById('name_en')?.value.trim() || '';
                const rawDescAr = document.getElementById('description_ar')?.value.trim() || '';
                const rawDescEn = document.getElementById('description_en')?.value.trim() || '';
                const canonical = document.getElementById('canonical_url')?.value.trim() || '';

                updateCharCounter('seo_title_ar', 60);
                updateCharCounter('seo_title_en', 60);
                updateCharCounter('meta_description_ar', 160);
                updateCharCounter('meta_description_en', 160);

                const isAr = '<?php echo get_current_lang(); ?>' === 'ar';
                const activeTitle = (isAr ? (titleAr || nameAr) : (titleEn || nameEn)) || 'Product Name';
                const siteBrand = isAr ? 'عالم الرفوف' : 'World of Shelves';
                const serpTitle = activeTitle + ' - ' + siteBrand;

                let activeDesc = isAr ? (descAr || rawDescAr) : (descEn || rawDescEn);
                if (!activeDesc) activeDesc = isAr ? 'سيتم توليد وصف البحث تلقائياً من المحتوى...' : 'A search engine snippet will be generated automatically...';
                if (activeDesc.length > 155) activeDesc = activeDesc.substring(0, 155) + '...';

                let currentSlug = document.getElementById('slug') ? document.getElementById('slug').value.trim() : '';
                if (!currentSlug) {
                    currentSlug = '<?php echo $product_to_edit && !empty($product_to_edit['slug']) ? htmlspecialchars($product_to_edit['slug']) : "NEW"; ?>';
                }
                const defaultUrl = 'https://worldofshelves.com/products/' + currentSlug;
                const serpUrl = canonical || defaultUrl;

                if (document.getElementById('serp_preview_title')) document.getElementById('serp_preview_title').innerText = serpTitle;
                if (document.getElementById('serp_preview_url')) document.getElementById('serp_preview_url').innerText = serpUrl;
                if (document.getElementById('serp_preview_desc')) document.getElementById('serp_preview_desc').innerText = activeDesc;

                let score = 0;
                const checks = [];

                const activeSeoTitle = isAr ? titleAr : titleEn;
                if (activeSeoTitle) {
                    if (activeSeoTitle.length >= 30 && activeSeoTitle.length <= 60) {
                        score += 30;
                        checks.push({ ok: true, msg: isAr ? 'عنوان SEO ممتاز (30-60 حرف)' : 'Optimal SEO Title length (30-60 chars)' });
                    } else {
                        score += 15;
                        checks.push({ ok: false, msg: isAr ? 'طول عنوان SEO غير مثالي (يفضل 30-60 حرف)' : 'SEO Title length non-optimal (recommended 30-60 chars)' });
                    }
                } else {
                    checks.push({ ok: false, msg: isAr ? 'عنوان SEO غير محدد (يتم استخدام الاسم افتراضياً)' : 'SEO Title not set (using fallback product name)' });
                }

                const activeMetaDesc = isAr ? descAr : descEn;
                if (activeMetaDesc) {
                    if (activeMetaDesc.length >= 70 && activeMetaDesc.length <= 160) {
                        score += 30;
                        checks.push({ ok: true, msg: isAr ? 'وصف Meta ممتاز (70-160 حرف)' : 'Optimal Meta Description length (70-160 chars)' });
                    } else {
                        score += 15;
                        checks.push({ ok: false, msg: isAr ? 'طول وصف Meta غير مثالي (يفضل 70-160 حرف)' : 'Meta Description length non-optimal (recommended 70-160 chars)' });
                    }
                } else {
                    checks.push({ ok: false, msg: isAr ? 'وصف Meta غير محدد (يتم اقتصاص الوصف العام افتراضياً)' : 'Meta Description not set (auto-truncating main description)' });
                }

                const altAr = document.getElementById('alt_text_ar')?.value.trim() || '';
                const altEn = document.getElementById('alt_text_en')?.value.trim() || '';
                if (altAr || altEn) {
                    score += 20;
                    checks.push({ ok: true, msg: isAr ? 'النص البديل Alt Text مضاف' : 'Image Alt Text configured' });
                } else {
                    checks.push({ ok: false, msg: isAr ? 'لم يتم إضافة نص بديل Alt Text (يتم استخدام الاسم افتراضياً)' : 'Image Alt Text not set (using product name fallback)' });
                }

                const ogSelect = document.getElementById('og_media_id')?.value || '';
                const ogFile = document.getElementById('og_image_file')?.files?.length || 0;
                if (ogSelect || ogFile || '<?php echo !empty($product_to_edit['og_media_id']); ?>') {
                    score += 20;
                    checks.push({ ok: true, msg: isAr ? 'صورة OpenGraph مخصصة مضافة' : 'Custom OpenGraph image selected' });
                } else {
                    checks.push({ ok: false, msg: isAr ? 'لم تحدد صورة OG مخصصة (يتم استخدام صورة المنتج الرئيسية افتراضياً)' : 'No custom OG image selected (using main product image)' });
                }

                const scoreEl = document.getElementById('seo_score_value');
                const fillEl = document.getElementById('seo_score_fill');
                const listEl = document.getElementById('seo_checklist');

                if (scoreEl) scoreEl.innerText = score + ' / 100';
                if (fillEl) {
                    fillEl.style.width = score + '%';
                    fillEl.style.backgroundColor = score >= 80 ? 'var(--success)' : (score >= 50 ? 'var(--warning)' : 'var(--danger)');
                }
                if (listEl) {
                    listEl.innerHTML = checks.map(c => `
                        <div style="display:flex; align-items:center; gap:6px; font-size:12px; color:${c.ok ? 'var(--success)' : '#d97706'}; margin-bottom:4px;">
                            <i data-lucide="${c.ok ? 'check-circle' : 'alert-circle'}" style="width:14px; height:14px; flex-shrink:0;"></i>
                            <span>${c.msg}</span>
                        </div>
                    `).join('');
                    if (window.lucide) lucide.createIcons();
                }
            }

            function moveAdminRowUp(btn) {
                const row = btn.closest('.admin-spec-row, .admin-related-row, .admin-card-row, .admin-sp-row, .admin-b-row, .admin-ind-row');
                if (row && row.previousElementSibling) {
                    row.parentNode.insertBefore(row, row.previousElementSibling);
                }
            }

            function moveAdminRowDown(btn) {
                const row = btn.closest('.admin-spec-row, .admin-related-row, .admin-card-row, .admin-sp-row, .admin-b-row, .admin-ind-row');
                if (row && row.nextElementSibling) {
                    row.parentNode.insertBefore(row.nextElementSibling, row);
                }
            }

            function addAdminTechSpecRow(kAr = '', kEn = '', vAr = '', vEn = '') {
                const container = document.getElementById('admin-specs-container');
                if (!container) return;
                const index = container.children.length + 1;
                const div = document.createElement('div');
                div.className = 'admin-spec-row';
                div.style.cssText = 'background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px; margin-bottom: 10px;';
                div.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid var(--border-color);">
                        <span style="font-weight: 700; font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                            <i data-lucide="grip-vertical" style="width: 14px; height: 14px; cursor: grab;"></i>
                            <span>#${index}</span>
                        </span>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="moveAdminRowUp(this)" title="Move Up" style="padding: 2px 6px; font-size: 11px;">▲</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="moveAdminRowDown(this)" title="Move Down" style="padding: 2px 6px; font-size: 11px;">▼</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="removeAdminSpecRow(this)" style="padding: 2px 6px; font-size: 11px; color: var(--danger);">✕</button>
                        </div>
                    </div>
                    <div class="form-row" style="margin-bottom: 6px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 11px;"><?php echo __('spec_key_ar'); ?></label>
                            <input type="text" name="tech_spec_key_ar[]" class="form-control" dir="rtl" value="${kAr}" placeholder="مثال: الحمولة القصوى">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 11px;"><?php echo __('spec_key_en'); ?></label>
                            <input type="text" name="tech_spec_key_en[]" class="form-control" dir="ltr" value="${kEn}" placeholder="e.g. Load Capacity">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 11px;"><?php echo __('spec_val_ar'); ?></label>
                            <input type="text" name="tech_spec_val_ar[]" class="form-control" dir="rtl" value="${vAr}" placeholder="مثال: 500 كجم/رف">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-size: 11px;"><?php echo __('spec_val_en'); ?></label>
                            <input type="text" name="tech_spec_val_en[]" class="form-control" dir="ltr" value="${vEn}" placeholder="e.g. 500 kg/shelf">
                        </div>
                    </div>
                `;
                container.appendChild(div);
                if (window.lucide) lucide.createIcons();
            }

            function removeAdminSpecRow(btn) {
                const row = btn.closest('.admin-spec-row');
                if (row) row.remove();
            }

            function addSelectedRelatedProduct() {
                const picker = document.getElementById('related-product-picker');
                if (!picker || !picker.value) return;
                const selectedOpt = picker.options[picker.selectedIndex];
                const pid = selectedOpt.value;
                const nameAr = selectedOpt.getAttribute('data-name-ar');
                const nameEn = selectedOpt.getAttribute('data-name-en');
                const cat = selectedOpt.getAttribute('data-category');
                const img = selectedOpt.getAttribute('data-img') || 'assets/images/shelf1.png';
                const isAr = '<?php echo get_current_lang(); ?>' === 'ar';
                const name = isAr ? nameAr : nameEn;

                const container = document.getElementById('admin-related-container');
                if (!container) return;

                const existing = container.querySelector(`.admin-related-row[data-id="${pid}"]`);
                if (existing) {
                    alert(isAr ? 'المنتج مضاف بالفعل لقائمة المنتجات ذات الصلة.' : 'Product is already added to related list.');
                    return;
                }

                const div = document.createElement('div');
                div.className = 'admin-related-row';
                div.setAttribute('data-id', pid);
                div.style.cssText = 'display: flex; align-items: center; justify-content: space-between; background: var(--bg-primary); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); margin-bottom: 8px;';
                div.innerHTML = `
                    <input type="hidden" name="related_product_ids[]" value="${pid}">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <img src="../${img}" style="width: 36px; height: 36px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border-color);">
                        <div>
                            <strong style="font-size: 13px; display: block;">${name}</strong>
                            <span style="font-size: 11px; color: var(--text-muted);">${cat}</span>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="moveAdminRowUp(this)" title="Move Up" style="padding: 2px 6px; font-size: 11px;">▲</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="moveAdminRowDown(this)" title="Move Down" style="padding: 2px 6px; font-size: 11px;">▼</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="removeAdminRelatedRow(this)" style="padding: 2px 6px; font-size: 11px; color: var(--danger);">✕</button>
                    </div>
                `;
                container.appendChild(div);
                picker.value = '';
            }

            function removeAdminRelatedRow(btn) {
                const row = btn.closest('.admin-related-row');
                if (row) row.remove();
            }

            function validateMaxGalleryImages(input, existingCount = 0) {
                if (!input || !input.files) return;
                const filesCount = input.files.length;
                const deleteCheckboxes = document.querySelectorAll('input[name="delete_gallery_ids[]"]:checked');
                const deleteCount = deleteCheckboxes ? deleteCheckboxes.length : 0;
                const netExisting = Math.max(0, existingCount - deleteCount);
                const totalAfterUpload = netExisting + filesCount;

                const isAr = '<?php echo get_current_lang(); ?>' === 'ar';

                if (filesCount > 4) {
                    alert(isAr ? 'عفواً، يمكنك تحديد 4 صور كحد أقصى للمعرض دفعة واحدة.' : 'Sorry, you can select a maximum of 4 gallery images at once.');
                    input.value = '';
                    return;
                }

                if (totalAfterUpload > 4) {
                    const remainingAllowed = Math.max(0, 4 - netExisting);
                    const msg = isAr 
                        ? `عفواً، الحد الأقصى المسموح به هو 4 صور إضافية للمعرض.\nلديك حالياً ${netExisting} صور، يمكنك إضافة ${remainingAllowed} صور فقط.`
                        : `Sorry, maximum limit is 4 gallery images.\nYou currently have ${netExisting} images, you can only upload ${remainingAllowed} more.`;
                    alert(msg);
                    input.value = '';
                    return;
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                updateSeoLivePreview();
                updateCmsCompletionProgress();
            });
            </script>
        <?php endif; ?>
<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
