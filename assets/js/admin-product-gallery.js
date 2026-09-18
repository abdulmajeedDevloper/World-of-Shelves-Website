/**
 * Admin Product Gallery, Colors, Models & Detail Cards Dynamic Helper
 * World of Shelves — Sprint V4
 */

function addAdminColorRow() {
    const container = document.getElementById('admin-colors-container');
    if (!container) return;

    const rowId = 'color-row-' + Date.now();
    const row = document.createElement('div');
    row.className = 'admin-dynamic-row';
    row.id = rowId;
    row.style.cssText = 'display: grid; grid-template-columns: 1fr 1fr 120px 40px; gap: 10px; align-items: center; margin-bottom: 10px; background: #f8fafc; padding: 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);';

    row.innerHTML = `
        <input type="text" name="new_color_name_ar[]" class="form-control" placeholder="اسم اللون (عربي)" dir="rtl" required>
        <input type="text" name="new_color_name_en[]" class="form-control" placeholder="Color Name (EN)" dir="ltr" required>
        <div style="display: flex; align-items: center; gap: 6px;">
            <input type="color" name="new_color_hex[]" class="form-control" value="#c27d38" style="width: 36px; height: 36px; padding: 2px; cursor: pointer;" onchange="this.nextElementSibling.value=this.value">
            <input type="text" name="new_color_hex_text[]" class="form-control" value="#c27d38" placeholder="#HEX" style="font-size: 11px; padding: 4px;" onchange="this.previousElementSibling.value=this.value">
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="removeAdminRow('${rowId}')" style="color: var(--danger); border-color: var(--danger-light); padding: 6px;">
            <i data-lucide="trash-2" style="width: 16px;"></i>
        </button>
    `;

    container.appendChild(row);
    if (window.lucide) window.lucide.createIcons();
}

function addAdminModelRow() {
    const container = document.getElementById('admin-models-container');
    if (!container) return;

    const rowId = 'model-row-' + Date.now();
    const row = document.createElement('div');
    row.className = 'admin-dynamic-row';
    row.id = rowId;
    row.style.cssText = 'display: grid; grid-template-columns: 1fr 1fr 1.5fr 40px; gap: 10px; align-items: center; margin-bottom: 10px; background: #f8fafc; padding: 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);';

    row.innerHTML = `
        <input type="text" name="new_model_name_ar[]" class="form-control" placeholder="اسم الموديل (عربي)" dir="rtl" required>
        <input type="text" name="new_model_name_en[]" class="form-control" placeholder="Model Name (EN)" dir="ltr" required>
        <input type="text" name="new_model_desc_ar[]" class="form-control" placeholder="الوصف القصيرة (اختياري)" dir="rtl">
        <button type="button" class="btn btn-secondary btn-sm" onclick="removeAdminRow('${rowId}')" style="color: var(--danger); border-color: var(--danger-light); padding: 6px;">
            <i data-lucide="trash-2" style="width: 16px;"></i>
        </button>
    `;

    container.appendChild(row);
    if (window.lucide) window.lucide.createIcons();
}

function removeAdminRow(rowId) {
    const row = document.getElementById(rowId);
    if (row) {
        row.remove();
    }
}

/**
 * Dynamic Row Generator for Product Detail Cards (Suitable For & Why Product)
 */
function addAdminCardRow(sectionType) {
    const containerId = (sectionType === 'why_product') ? 'admin-why-container' : 'admin-suitable-container';
    const container = document.getElementById(containerId);
    if (!container) return;

    const defaultIcon = (sectionType === 'why_product') ? 'shield' : 'check-square';
    const row = document.createElement('div');
    row.className = 'admin-card-row';
    row.style.cssText = 'background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 12px;';

    const iconsList = ['warehouse', 'store', 'archive', 'home', 'shield', 'weight', 'layers', 'check-circle', 'star', 'sparkles', 'package', 'box', 'truck', 'award', 'wrench', 'settings', 'check-square', 'cpu', 'clock', 'zap', 'lock', 'eye', 'thumbs-up', 'heart', 'ruler', 'shield-check', 'package-check'];
    let iconOptionsHtml = '';
    iconsList.forEach(ic => {
        const sel = (ic === defaultIcon) ? 'selected' : '';
        iconOptionsHtml += `<option value="${ic}" ${sel}>${ic}</option>`;
    });

    row.innerHTML = `
        <input type="hidden" name="card_id[]" value="0">
        <input type="hidden" name="card_section[]" value="${sectionType}">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="${defaultIcon}" class="icon-preview-badge" style="width:18px; height:18px; color: var(--accent-primary);"></i>
                <strong style="font-size: 13px;">بطاقة جديدة / New Card</strong>
            </div>
            
            <div style="display: flex; align-items: center; gap: 6px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="moveAdminRowUp(this)" title="Move Up" style="padding: 2px 6px; font-size: 11px;">▲</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="moveAdminRowDown(this)" title="Move Down" style="padding: 2px 6px; font-size: 11px;">▼</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="this.closest('.admin-card-row').remove()" style="color: var(--danger); border-color: var(--danger-light); padding: 2px 6px; font-size: 11px;">✕</button>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label style="font-size: 11px;">العنوان بالعربية</label>
                <input type="text" name="card_title_ar[]" class="form-control" dir="rtl" placeholder="عنوان البطاقة بالعربية" required>
            </div>
            <div class="form-group">
                <label style="font-size: 11px;">English Title</label>
                <input type="text" name="card_title_en[]" class="form-control" dir="ltr" placeholder="Card Title in English" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label style="font-size: 11px;">الوصف بالعربية (اختياري)</label>
                <input type="text" name="card_desc_ar[]" class="form-control" dir="rtl" placeholder="وصف مكمل (اختياري)">
            </div>
            <div class="form-group">
                <label style="font-size: 11px;">English Description (Optional)</label>
                <input type="text" name="card_desc_en[]" class="form-control" dir="ltr" placeholder="Optional description">
            </div>
        </div>

        <div style="display: flex; gap: 12px; align-items: center; margin-top: 6px;">
            <div class="form-group" style="flex: 1; margin: 0;">
                <label style="font-size: 11px;">الأيقونة (Lucide Icon)</label>
                <select name="card_icon[]" class="form-control" onchange="previewAdminIcon(this)" style="padding: 4px 8px; font-size: 12px;">
                    ${iconOptionsHtml}
                </select>
            </div>
            <div class="form-group" style="width: 90px; margin: 0;">
                <label style="font-size: 11px;">الترتيب</label>
                <input type="number" name="card_sort[]" class="form-control" dir="ltr" value="0" style="padding: 4px 8px; font-size: 12px;">
            </div>
            <div class="form-group" style="margin: 0; display: flex; align-items: flex-end; padding-bottom: 4px;">
                <label style="display: flex; align-items: center; gap: 4px; font-size: 12px; cursor: pointer;">
                    <input type="checkbox" name="card_active[]" value="1" checked>
                    <span>نشط</span>
                </label>
            </div>
        </div>
    `;

    container.appendChild(row);
    if (window.lucide) window.lucide.createIcons();
}

/**
 * Move row up in the DOM & update sort orders
 */
function moveAdminRowUp(btn) {
    const row = btn.closest('.admin-card-row');
    if (!row) return;
    const previous = row.previousElementSibling;
    if (previous && previous.classList.contains('admin-card-row')) {
        row.parentNode.insertBefore(row, previous);
        updateRowSortOrders(row.parentNode);
    }
}

/**
 * Move row down in the DOM & update sort orders
 */
function moveAdminRowDown(btn) {
    const row = btn.closest('.admin-card-row');
    if (!row) return;
    const next = row.nextElementSibling;
    if (next && next.classList.contains('admin-card-row')) {
        row.parentNode.insertBefore(next, row);
        updateRowSortOrders(row.parentNode);
    }
}

function updateRowSortOrders(container) {
    if (!container) return;
    const rows = container.querySelectorAll('.admin-card-row');
    rows.forEach((r, idx) => {
        const sortInput = r.querySelector('input[name="card_sort[]"]');
        if (sortInput) {
            sortInput.value = (idx + 1) * 10;
        }
    });
}

/**
 * Live icon previewer
 */
function previewAdminIcon(selectEl) {
    const cardRow = selectEl.closest('.admin-card-row');
    if (!cardRow) return;
    const iconBadge = cardRow.querySelector('.icon-preview-badge');
    if (iconBadge) {
        iconBadge.setAttribute('data-lucide', selectEl.value);
        if (window.lucide) window.lucide.createIcons();
    }
}
