/**
 * Handdn Navigation & Menu Manager - Modern Minimalist Admin Engine (Shadcn UI style)
 *
 * @package Device_Menu_Manager
 */

(function ($) {
  'use strict';

  var DMM = {
    settings: window.dmmAdmin ? window.dmmAdmin.settings || {} : {},
    data: window.dmmAdmin ? (window.dmmAdmin.menuData || window.dmmAdmin.desktopData || []) : [],
    collapsed: {},
    activeInspectorId: null,
    pendingDelete: null,
    mediaFrame: null,

    /**
     * Initialize builder.
     */
    init: function () {
      // Deduplicate initial data by db_id
      var uniqueData = [];
      var seenInit = {};
      for (var k = 0; k < (this.data || []).length; k++) {
        var itk = this.data[k];
        if (itk && itk.db_id) {
          var sid = String(itk.db_id);
          if (!seenInit[sid]) {
            seenInit[sid] = true;
            uniqueData.push(itk);
          }
        }
      }
      this.data = uniqueData;

      this.bindTabs();
      this.initColorPickers();
      this.initMediaUploaders();
      this.initInspectorSheet();
      this.initDeleteModal();
      this.initDefaultCollapsed();
      this.renderTree();
      this.bindTreeActions();
      this.bindToolbarButtons();
      this.bindJsonPanel();
      this.bindImportAndReset();
      this.bindCollapseToggles();
      this.bindGlobalSave();
    },

    /**
     * Initialize WordPress Color Pickers.
     */
    initColorPickers: function () {
      if ($.fn.wpColorPicker) {
        $('.dmm-color-picker').each(function () {
          if (!$(this).closest('.wp-picker-container').length) {
            $(this).wpColorPicker();
          }
        });
      }
    },

    /**
     * Set media URL and sync all inputs and preview thumbnails for a section.
     */
    setMediaUrl: function (prefix, url) {
      url = url ? String(url).trim() : '';
      $('#dmm-' + prefix + '-image-url').val(url);
      $('#dmm-' + prefix + '-image-url-input').val(url);
      var $previewWrap = $('.dmm-' + prefix + '-preview-wrap');
      var $previewImg = $('.dmm-' + prefix + '-preview img');
      if (url) {
        $previewImg.attr('src', url);
        $previewWrap.show();
      } else {
        $previewImg.attr('src', '');
        $previewWrap.hide();
      }
    },

    /**
     * Initialize WordPress Media Uploaders and Image Source Controls.
     */
    initMediaUploaders: function () {
      var self = this;
      $(document).on('click', '.dmm-btn-select-media', function (e) {
        e.preventDefault();
        var prefix = $(this).data('prefix');
        var targetInput = $(this).data('target');
        var $input = targetInput ? $(targetInput) : (prefix ? $('#dmm-' + prefix + '-image-url') : null);

        if (self.mediaFrame) {
          self.mediaFrame.targetPrefix = prefix;
          self.mediaFrame.targetInput = $input;
          self.mediaFrame.open();
          return;
        }

        self.mediaFrame = wp.media({
          title: 'Select or Upload Image',
          button: { text: 'Use This Image' },
          multiple: false
        });

        self.mediaFrame.targetPrefix = prefix;
        self.mediaFrame.targetInput = $input;

        self.mediaFrame.on('select', function () {
          var attachment = self.mediaFrame.state().get('selection').first().toJSON();
          if (attachment && attachment.url) {
            if (self.mediaFrame.targetPrefix) {
              self.setMediaUrl(self.mediaFrame.targetPrefix, attachment.url);
            } else if (self.mediaFrame.targetInput) {
              self.mediaFrame.targetInput.val(attachment.url).trigger('change');
            }
          }
        });

        self.mediaFrame.open();
      });

      // Remove media button handler
      $(document).on('click', '.dmm-btn-remove-media', function (e) {
        e.preventDefault();
        var prefix = $(this).data('prefix') || ($(this).data('target') ? $(this).data('target').replace('#dmm-', '').replace('-image-url', '') : '');
        if (prefix) {
          self.setMediaUrl(prefix, '');
        }
      });

      // Synchronize media fields and toggle options for Promo, Visual Banner, Feature Card
      ['promo', 'insp-visual', 'insp-feature'].forEach(function (prefix) {
        $('#dmm-' + prefix + '-image-url-input').on('input change', function () {
          self.setMediaUrl(prefix, $(this).val());
        });

        $('#dmm-' + prefix + '-media-type').on('change', function () {
          var isUrl = $(this).val() === 'url';
          $('.dmm-' + prefix + '-upload-row').toggle(!isUrl);
          $('.dmm-' + prefix + '-url-row').toggle(isUrl);
        });
      });

      // Initial sync for Promo Banner
      var initialPromoUrl = $('#dmm-promo-image-url').val();
      if (initialPromoUrl) {
        self.setMediaUrl('promo', initialPromoUrl);
      }

      // Trustpilot auto-detect toggle in Tab 2
      function syncTpAutoState() {
        var isAuto = $('#dmm-tp-auto').is(':checked');
        var isConnected = $('#dmm-tp-status-notice').hasClass('dmm-notice-connected');
        $('#dmm-tp-status-notice').css('display', isAuto ? 'flex' : 'none');
        // If auto is ON but plugin is NOT connected, keep manual fields visible so fallback is clear and editable
        $('.dmm-tp-manual-row').toggle(!isAuto || !isConnected);
      }
      $('#dmm-tp-auto').on('change', syncTpAutoState);
      syncTpAutoState();
    },

    /**
     * Initialize default collapsed state.
     */
    initDefaultCollapsed: function () {
      var items = this.data || [];
      this.collapsed = this.collapsed || {};
      for (var i = 0; i < items.length; i++) {
        if (this.countDirectChildren(items[i].db_id) > 0) {
          this.collapsed[String(items[i].db_id)] = true;
        }
      }
    },

    /**
     * Show notification toast.
     */
    showToast: function (message, isError) {
      var $toast = $('#dmm-toast');
      $toast.removeClass('dmm-toast-error');
      if (isError) $toast.addClass('dmm-toast-error');
      $toast.find('.dmm-toast-message').text(message);
      $toast.stop(true, true).fadeIn(150);

      clearTimeout(this._toastTimer);
      this._toastTimer = setTimeout(function () {
        $toast.fadeOut(250);
      }, 3200);
    },

    /**
     * Bind Main Navigation Tabs.
     */
    bindTabs: function () {
      $('.dmm-tabs-nav .dmm-tab-trigger').on('click', function (e) {
        e.preventDefault();
        var tabId = $(this).data('tab');
        $('.dmm-tabs-nav .dmm-tab-trigger').removeClass('active');
        $(this).addClass('active');

        $('.dmm-tab-panel').removeClass('active');
        $('#dmm-tab-' + tabId).addClass('active');
      });
    },

    /**
     * Calculate item hierarchy depth from flat parent relations.
     */
    getItemDepth: function (items, item) {
      var depth = 0;
      var curParent = String(item.menu_item_parent || '0');
      var visited = {};

      while (curParent !== '0' && curParent !== '' && !visited[curParent] && depth < 12) {
        visited[curParent] = true;
        var found = false;
        for (var i = 0; i < items.length; i++) {
          if (String(items[i].db_id) === curParent) {
            depth++;
            curParent = String(items[i].menu_item_parent || '0');
            found = true;
            break;
          }
        }
        if (!found) break;
      }
      return depth;
    },

    /**
     * Find item by ID.
     */
    findItem: function (id) {
      for (var i = 0; i < this.data.length; i++) {
        if (parseInt(this.data[i].db_id, 10) === parseInt(id, 10)) {
          return this.data[i];
        }
      }
      return null;
    },

    /**
     * Count direct children of an item.
     */
    countDirectChildren: function (parentId) {
      var count = 0;
      for (var i = 0; i < this.data.length; i++) {
        if (String(this.data[i].menu_item_parent) === String(parentId)) {
          count++;
        }
      }
      return count;
    },

    /**
     * Count all descendants recursively.
     */
    countTotalDescendants: function (parentId) {
      return this.getDescendantIds(parentId).length;
    },

    /**
     * Get all descendant IDs of an item recursively.
     */
    getDescendantIds: function (parentId) {
      var descendants = [];
      var queue = [String(parentId)];
      var visited = {};
      visited[String(parentId)] = true;

      while (queue.length > 0) {
        var curParent = queue.shift();
        for (var i = 0; i < this.data.length; i++) {
          if (String(this.data[i].menu_item_parent) === curParent) {
            var childId = String(this.data[i].db_id);
            if (!visited[childId]) {
              visited[childId] = true;
              descendants.push(childId);
              queue.push(childId);
            }
          }
        }
      }

      return descendants;
    },

    /**
     * Find ancestor ID at a specific target depth.
     */
    findAncestorAtDepth: function (startId, targetDepth) {
      if (targetDepth < 0) return '0';
      var cur = this.findItem(startId);

      while (cur) {
        var d = this.getItemDepth(this.data, cur);
        if (d === targetDepth) return String(cur.db_id);
        if (cur.menu_item_parent === '0' || !cur.menu_item_parent) break;
        cur = this.findItem(cur.menu_item_parent);
      }

      return '0';
    },

    /**
     * Render hierarchical tree.
     */
    renderTree: function () {
      var items = this.data || [];
      var $tree = $('#dmm-tree-main');
      var $empty = $('#dmm-empty-main');
      var $count = $('#dmm-count-main');

      $count.text(items.length + ' items');

      if (items.length === 0) {
        $tree.empty();
        $empty.show();
        return;
      }

      $empty.hide();
      $tree.empty();

      var hiddenIds = {};
      var collapsedMap = this.collapsed || {};

      for (var colId in collapsedMap) {
        if (collapsedMap[colId]) {
          var descIds = this.getDescendantIds(colId);
          for (var d = 0; d < descIds.length; d++) {
            hiddenIds[descIds[d]] = true;
          }
        }
      }

      for (var i = 0; i < items.length; i++) {
        var item = items[i];
        var depth = this.getItemDepth(items, item);
        var $node = this.createItemNode(item, depth);

        if (hiddenIds[String(item.db_id)]) {
          $node.hide();
        }

        $tree.append($node);
      }

      this.initSortable();
      this.updateJsonTextarea();
    },

    /**
     * Create single item DOM element (Shadcn minimalist row).
     */
    createItemNode: function (item, depth) {
      var itemType = item.item_type || 'link';
      var isRoot = (depth === 0);

      // Depth badge
      var depthBadgeHtml = '<span class="dmm-badge dmm-badge-depth">L' + depth + '</span>';

      // Section tag for Root items
      var sectionHtml = '';
      if (isRoot) {
        var isSec = (item.section === 'secondary');
        sectionHtml = '<button type="button" class="dmm-badge ' + (isSec ? 'dmm-badge-secondary' : 'dmm-badge-primary') + ' dmm-section-btn" title="Click to toggle Primary / Secondary">' + (isSec ? 'SECONDARY' : 'PRIMARY') + '</button>';
      }

      // Type Badge
      var typeBadgeHtml = '';
      if (itemType === 'heading') {
        typeBadgeHtml = '<span class="dmm-badge dmm-badge-heading">HEADING</span>';
      } else if (itemType === 'sizes_grid') {
        typeBadgeHtml = '<span class="dmm-badge dmm-badge-sizes">GRID LAYOUT</span>';
      }

      // Visual Banner Badge (Clickable to edit visual directly)
      var visualBadgeHtml = '';
      if (item.visual && (item.visual.enabled || item.visual.headline || item.visual.kicker)) {
        visualBadgeHtml = '<button type="button" class="dmm-badge dmm-badge-visual dmm-btn-open-visual" title="Edit Dynamic Visual Banner">✦ VISUAL</button>';
      }

      // Feature Card Badge
      var featureBadgeHtml = '';
      if (item.feature && (item.feature.enabled || item.feature.text)) {
        featureBadgeHtml = '<span class="dmm-badge dmm-badge-feature" title="Sub-panel Feature Card active">FEATURE</span>';
      }

      // Custom Tag
      var tagHtml = '';
      if (item.tag && String(item.tag).trim()) {
        tagHtml = '<span class="dmm-badge dmm-badge-tag">' + $('<div>').text(item.tag).html() + '</span>';
      }

      // URL Preview
      var urlPreview = '';
      if (itemType === 'heading') {
        urlPreview = '<span class="dmm-item-url-preview">&mdash; Section Header &mdash;</span>';
      } else if (itemType === 'sizes_grid') {
        urlPreview = '<span class="dmm-item-url-preview">&mdash; Lug Width Grid &mdash;</span>';
      } else if (item.url && item.url !== '#') {
        urlPreview = '<span class="dmm-item-url-preview">' + $('<div>').text(item.url).html() + '</span>';
      }

      // Collapse button
      var childCount = this.countDirectChildren(item.db_id);
      var totalDesc = this.countTotalDescendants(item.db_id);
      var isCollapsed = Boolean(this.collapsed[String(item.db_id)]);
      var collapseHtml = '';
      var subBadgeHtml = '';

      if (childCount > 0) {
        collapseHtml = '<button type="button" class="dmm-collapse-btn" title="Toggle sub-menu"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></button>';
        if (isCollapsed) {
          subBadgeHtml = '<span class="dmm-sub-count-badge" title="Click to expand">' + totalDesc + ' sub</span>';
        }
      } else {
        collapseHtml = '<span class="dmm-collapse-placeholder"></span>';
      }

      var itemTitleDisplay = item.title ? $('<div>').text(item.title).html() : '(No Title)';
      var safeDbId = parseInt(item.db_id, 10) || 0;
      var safeParentId = String(item.menu_item_parent || '0').replace(/[^0-9]/g, '') || '0';

      var html = [
        '<li class="dmm-tree-item' + (isCollapsed ? ' dmm-is-collapsed' : '') + (itemType === 'heading' ? ' dmm-is-heading' : '') + '" data-id="' + safeDbId + '" data-parent="' + safeParentId + '" data-depth="' + depth + '">',
          '<div class="dmm-item-card">',
            '<div class="dmm-item-handle" title="Drag to reorder / indent"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="12" r="1"/><circle cx="9" cy="5" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="19" r="1"/></svg></div>',
            collapseHtml,
            '<div class="dmm-item-main">',
              depthBadgeHtml,
              sectionHtml,
              '<strong class="dmm-item-title">' + itemTitleDisplay + '</strong>',
              typeBadgeHtml,
              visualBadgeHtml,
              featureBadgeHtml,
              tagHtml,
              subBadgeHtml,
              urlPreview,
            '</div>',
            '<div class="dmm-item-actions">',
              '<button type="button" class="dmm-btn dmm-btn-sm dmm-btn-outline dmm-btn-add-subitem" title="Add child item directly under this item">+ Sub</button>',
              '<button type="button" class="dmm-btn dmm-btn-sm dmm-btn-outline dmm-btn-edit-inspector">Edit</button>',
              '<button type="button" class="dmm-btn dmm-btn-sm dmm-btn-ghost dmm-btn-icon dmm-btn-delete-item" title="Delete"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>',
            '</div>',
          '</div>',
        '</li>'
      ].join('');

      return $(html);
    },

    /**
     * Initialize Hierarchical Drag & Drop sortable.
     */
    initSortable: function () {
      var self = this;
      var $tree = $('#dmm-tree-main');

      if ($tree.hasClass('ui-sortable')) {
        $tree.sortable('destroy');
      }

      var currentTargetDepth = 0;

      $tree.sortable({
        handle: '.dmm-item-handle',
        placeholder: 'dmm-sortable-placeholder',
        tolerance: 'pointer',
        distance: 5,
        start: function (e, ui) {
          var initialDepth = parseInt(ui.item.attr('data-depth'), 10) || 0;
          currentTargetDepth = initialDepth;
          ui.placeholder.attr('data-depth', initialDepth);
        },
        sort: function (e, ui) {
          var listLeft = $tree.offset().left;
          var pointerX = e.pageX;
          var relativeX = Math.max(0, pointerX - listLeft);
          var calculatedDepth = Math.floor(relativeX / 32);

          var $prev = ui.placeholder.prevAll('.dmm-tree-item:not(.ui-sortable-helper):visible').first();
          var maxAllowedDepth = 0;

          if ($prev.length) {
            var prevId = $prev.attr('data-id');
            var prevItem = self.findItem(prevId);
            var prevDepth = prevItem ? self.getItemDepth(self.data, prevItem) : (parseInt($prev.attr('data-depth'), 10) || 0);
            maxAllowedDepth = prevDepth + 1;
          }

          currentTargetDepth = Math.max(0, Math.min(calculatedDepth, maxAllowedDepth, 8));
          ui.placeholder.attr('data-depth', currentTargetDepth);
          ui.placeholder.css('margin-left', (currentTargetDepth * 32) + 'px');
        },
        stop: function (e, ui) {
          var itemId = ui.item.attr('data-id');
          var targetItem = self.findItem(itemId);
          if (!targetItem) {
            self.renderTree();
            return;
          }

          // Step 1: Find the visible item directly preceding ui.item in the DOM
          var $prev = ui.item.prevAll('.dmm-tree-item:visible').first();
          var newParentId = '0';

          if ($prev.length && currentTargetDepth > 0) {
            var prevId = $prev.attr('data-id');
            var prevItem = self.findItem(prevId);
            if (prevItem) {
              var prevDepth = self.getItemDepth(self.data, prevItem);

              // Constrain depth: item cannot be more than prevDepth + 1
              if (currentTargetDepth > prevDepth + 1) {
                currentTargetDepth = prevDepth + 1;
              }

              if (currentTargetDepth === prevDepth + 1) {
                // Indented under prevItem -> direct child of prevItem
                newParentId = String(prevItem.db_id);
              } else if (currentTargetDepth === prevDepth) {
                // Sibling of prevItem -> share same parent
                newParentId = String(prevItem.menu_item_parent || '0');
              } else {
                // Outdented (currentTargetDepth < prevDepth) -> scan upward for ancestor at depth (currentTargetDepth - 1)
                newParentId = self.findAncestorAtDepth(prevId, currentTargetDepth - 1);
              }
            }
          } else {
            currentTargetDepth = 0;
            newParentId = '0';
          }

          // Circular parenting prevention: item cannot be parent of itself or its descendants
          var descIds = self.getDescendantIds(itemId);
          if (newParentId === String(itemId) || descIds.indexOf(newParentId) !== -1) {
            newParentId = '0';
            currentTargetDepth = 0;
          }

          // Assign new parent
          targetItem.menu_item_parent = newParentId;

          // If item is not root, inherit section from parent's root ancestor
          if (newParentId !== '0') {
            var rootParentId = self.findAncestorAtDepth(newParentId, 0);
            var rootItem = self.findItem(rootParentId);
            if (rootItem && rootItem.section) {
              targetItem.section = rootItem.section;
            }
          }

          // Step 2: Keep tree structure contiguous: move any descendant DOM nodes immediately after ui.item
          var $last = ui.item;
          for (var d = 0; d < descIds.length; d++) {
            var $descNode = $tree.find('> .dmm-tree-item[data-id="' + descIds[d] + '"]');
            if ($descNode.length) {
              $last.after($descNode);
              $last = $descNode;
            }
          }

          // Step 3: Re-sequence items strictly in DOM order (1-to-1 array index matching)
          var newOrderedData = [];
          var seenIds = {};

          $tree.find('> .dmm-tree-item').each(function () {
            var id = $(this).attr('data-id');
            if (id && !seenIds[id]) {
              seenIds[id] = true;
              var it = self.findItem(id);
              if (it) {
                newOrderedData.push(it);
              }
            }
          });

          // Any items not encountered in DOM (e.g. collapsed or detached) appended safely
          for (var i = 0; i < self.data.length; i++) {
            var itm = self.data[i];
            if (itm && !seenIds[String(itm.db_id)]) {
              seenIds[String(itm.db_id)] = true;
              newOrderedData.push(itm);
            }
          }

          self.data = newOrderedData;
          self.renderTree();
        }
      });
    },

    /**
     * Bind Tree Actions.
     */
    bindTreeActions: function () {
      var self = this;

      // Click section badge (Primary / Secondary) to toggle
      $(document).on('click', '.dmm-section-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $li = $(this).closest('.dmm-tree-item');
        var item = self.findItem($li.attr('data-id'));
        if (item) {
          item.section = (item.section === 'secondary') ? 'primary' : 'secondary';
          self.renderTree();
          self.showToast('Set section to ' + item.section.toUpperCase(), false);
        }
      });

      // Add Sub-item directly under this item
      $(document).on('click', '.dmm-btn-add-subitem', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var parentId = $(this).closest('.dmm-tree-item').attr('data-id');
        var newId = Date.now();
        var parentItem = self.findItem(parentId);
        var subTitle = 'New Item';
        if (parentItem && parentItem.item_type === 'sizes_grid') {
          subTitle = '20mm';
        }
        var newItem = {
          db_id: newId,
          title: subTitle,
          url: '#',
          menu_item_parent: String(parentId),
          item_type: 'link',
          tag: '',
          classes: [''],
          visual: { enabled: false },
          feature: { enabled: false }
        };
        // Insert right after parent in data array
        var pIdx = -1;
        for (var i = 0; i < self.data.length; i++) {
          if (String(self.data[i].db_id) === String(parentId)) {
            pIdx = i;
            break;
          }
        }
        if (pIdx !== -1) {
          self.data.splice(pIdx + 1, 0, newItem);
        } else {
          self.data.push(newItem);
        }
        delete self.collapsed[String(parentId)];
        self.renderTree();
        self.openInspector(newId, 'content');
        self.showToast('Added new child under "' + (parentItem ? parentItem.title : 'item') + '"', false);
      });

      // Open Edit Inspector Sheet
      $(document).on('click', '.dmm-btn-edit-inspector', function (e) {
        e.preventDefault();
        var id = $(this).closest('.dmm-tree-item').attr('data-id');
        self.openInspector(id, 'content');
      });

      // Open Visual Banner Tab directly
      $(document).on('click', '.dmm-btn-open-visual', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var id = $(this).closest('.dmm-tree-item').attr('data-id');
        self.openInspector(id, 'visual');
      });

      // Delete Item
      $(document).on('click', '.dmm-btn-delete-item', function (e) {
        e.preventDefault();
        var $li = $(this).closest('.dmm-tree-item');
        var id = $li.attr('data-id');
        var item = self.findItem(id);
        self.openDeleteModal(id, item ? item.title : '');
      });

      // Collapse / Expand toggle
      $(document).on('click', '.dmm-collapse-btn, .dmm-sub-count-badge', function (e) {
        e.preventDefault();
        var $li = $(this).closest('.dmm-tree-item');
        var id = $li.attr('data-id');
        self.collapsed[String(id)] = !self.collapsed[String(id)];
        self.renderTree();
      });
    },

    /**
     * Bind Toolbar Add buttons.
     */
    bindToolbarButtons: function () {
      var self = this;

      $('.dmm-btn-add-item').on('click', function (e) {
        e.preventDefault();
        var id = Date.now();
        self.data.push({
          db_id: id,
          title: 'New Link',
          url: '#',
          menu_item_parent: '0',
          section: 'primary',
          item_type: 'link',
          tag: '',
          classes: [''],
          visual: { enabled: false },
          feature: { enabled: false }
        });
        self.renderTree();
        self.openInspector(id, 'content');
      });

      $('.dmm-btn-add-heading').on('click', function (e) {
        e.preventDefault();
        var id = Date.now();
        self.data.push({
          db_id: id,
          title: 'NEW GROUP SECTION',
          url: '#',
          menu_item_parent: '0',
          section: 'primary',
          item_type: 'heading',
          tag: '',
          classes: [''],
          visual: { enabled: false },
          feature: { enabled: false }
        });
        self.renderTree();
        self.openInspector(id, 'content');
      });

      $('.dmm-btn-add-sizes').on('click', function (e) {
        e.preventDefault();
        var id = Date.now();
        self.data.push({
          db_id: id,
          title: 'Shop by lug width',
          url: '#',
          menu_item_parent: '0',
          section: 'primary',
          item_type: 'sizes_grid',
          tag: '',
          classes: [''],
          visual: { enabled: true, tex: 'smooth', tone: '#b07a3a', kicker: 'Lug width', headline: 'Every strap from 14 to 26mm, in 1mm steps.', cta_text: 'How to measure', cta_url: 'https://handdn.us/support-center/how-to-measure-exactly-watch-strap/' },
          feature: { enabled: false }
        });
        self.renderTree();
        self.openInspector(id, 'visual');
      });
    },

    /**
     * Initialize Slide-over Inspector Sheet.
     */
    initInspectorSheet: function () {
      var self = this;
      var $sheet = $('#dmm-inspector-sheet');

      // Tab switcher inside inspector sheet
      $sheet.on('click', '.dmm-sheet-tab-btn', function (e) {
        e.preventDefault();
        var tabKey = $(this).data('tab');
        $sheet.find('.dmm-sheet-tab-btn').removeClass('active');
        $(this).addClass('active');

        $sheet.find('.dmm-sheet-panel').removeClass('active');
        $('#dmm-insp-panel-' + tabKey).addClass('active');
      });

      // Item type dropdown change
      $('#dmm-insp-type').on('change', function () {
        var type = $(this).val();
        var isLink = (type === 'link');
        $('#dmm-insp-url-group').toggle(isLink);
        if (type === 'heading') {
          $('#dmm-insp-type-desc').text('Displays as uppercase section heading. Items under it will be grouped together.');
        } else if (type === 'sizes_grid') {
          $('#dmm-insp-type-desc').text('Renders interactive multi-column grid of pill buttons (e.g. 14mm, 16mm or custom sizes). Each pill dynamically takes the name and URL of its child items nested below.');
        } else {
          $('#dmm-insp-type-desc').text('');
        }
      });

      // Visual enabled toggle
      $('#dmm-insp-visual-enabled').on('change', function () {
        $('#dmm-insp-visual-fields').toggle($(this).is(':checked'));
      });

      // Feature enabled toggle
      $('#dmm-insp-feature-enabled').on('change', function () {
        $('#dmm-insp-feature-fields').toggle($(this).is(':checked'));
      });

      // Close sheet
      $sheet.on('click', '.dmm-sheet-close-btn, .dmm-btn-close-inspector', function (e) {
        e.preventDefault();
        self.closeInspector();
      });

      // Apply changes button
      $sheet.on('click', '.dmm-btn-apply-inspector', function (e) {
        e.preventDefault();
        self.applyInspector();
      });

      // Close on backdrop click
      $sheet.on('click', function (e) {
        if ($(e.target).is('#dmm-inspector-sheet')) {
          self.closeInspector();
        }
      });

      // Close on Escape key
      $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $sheet.is(':visible')) {
          if ($('.media-modal:visible').length) return;
          self.closeInspector();
        }
      });
    },

    /**
     * Open Inspector Sheet and populate item values.
     */
    openInspector: function (id, initialTab) {
      this.activeInspectorId = id;
      var item = this.findItem(id);
      if (!item) return;

      var $sheet = $('#dmm-inspector-sheet');
      var depth = this.getItemDepth(this.data, item);

      $('#dmm-sheet-item-title-preview').text(item.title || '(No Title)');
      $('#dmm-sheet-item-depth-preview').text('Level ' + depth + (depth === 0 ? ' (Root)' : ''));

      var activeTab = (initialTab && ['content', 'visual', 'feature'].indexOf(initialTab) !== -1) ? initialTab : 'content';

      $sheet.find('.dmm-sheet-tab-btn').removeClass('active');
      $sheet.find('.dmm-sheet-tab-btn[data-tab="' + activeTab + '"]').addClass('active');
      $sheet.find('.dmm-sheet-panel').removeClass('active');
      $('#dmm-insp-panel-' + activeTab).addClass('active');

      // Tab 1: Content
      $('#dmm-insp-title').val(item.title || '');
      $('#dmm-insp-type').val(item.item_type || 'link').trigger('change');
      $('#dmm-insp-url').val(item.url || '#');
      $('#dmm-insp-tag').val(item.tag || '');
      $('#dmm-insp-classes').val((item.classes || []).join(' '));

      var isRoot = String(item.menu_item_parent || '0') === '0';
      $('#dmm-insp-section-group').toggle(isRoot);
      $('#dmm-insp-section').val(item.section || 'primary');

      // Tab 2: Visual Banner
      var visual = item.visual || {};
      var vEnabled = Boolean(visual.enabled);
      $('#dmm-insp-visual-enabled').prop('checked', vEnabled);
      $('#dmm-insp-visual-fields').toggle(vEnabled);
      var vMediaType = (visual.media_type === 'url') ? 'url' : 'upload';
      $('#dmm-insp-visual-media-type').val(vMediaType).trigger('change');
      this.setMediaUrl('insp-visual', visual.image_url || '');

      $('#dmm-insp-visual-kicker').val(visual.kicker || '');
      $('#dmm-insp-visual-headline').val(visual.headline || '');
      $('#dmm-insp-visual-cta-text').val(visual.cta_text || '');
      $('#dmm-insp-visual-cta-url').val(visual.cta_url || '');

      // Tab 3: Feature Card
      var feature = item.feature || {};
      var fEnabled = Boolean(feature.enabled);
      $('#dmm-insp-feature-enabled').prop('checked', fEnabled);
      $('#dmm-insp-feature-fields').toggle(fEnabled);
      var fMediaType = (feature.media_type === 'url') ? 'url' : 'upload';
      $('#dmm-insp-feature-media-type').val(fMediaType).trigger('change');
      this.setMediaUrl('insp-feature', feature.image_url || '');

      $('#dmm-insp-feature-kicker').val(feature.kicker || '');
      $('#dmm-insp-feature-text').val(feature.text || '');
      $('#dmm-insp-feature-cta-text').val(feature.cta_text || '');
      $('#dmm-insp-feature-cta-url').val(feature.cta_url || '');

      $sheet.fadeIn(150);
      $('body').css('overflow', 'hidden');
    },

    /**
     * Close Inspector Sheet.
     */
    closeInspector: function () {
      $('#dmm-inspector-sheet').fadeOut(150);
      $('body').css('overflow', '');
      this.activeInspectorId = null;
    },

    /**
     * Apply Inspector Sheet values to active item.
     */
    applyInspector: function () {
      if (!this.activeInspectorId) return;
      var item = this.findItem(this.activeInspectorId);
      if (!item) return;

      item.title = $('#dmm-insp-title').val().trim();
      item.item_type = $('#dmm-insp-type').val();
      item.url = (item.item_type === 'link') ? $('#dmm-insp-url').val().trim() : '#';
      item.tag = $('#dmm-insp-tag').val().trim();
      item.classes = $('#dmm-insp-classes').val().trim().split(/\s+/).filter(Boolean);

      var isRoot = String(item.menu_item_parent || '0') === '0';
      if (isRoot) {
        item.section = $('#dmm-insp-section').val();
      }

      item.visual = {
        enabled: $('#dmm-insp-visual-enabled').is(':checked'),
        media_type: $('#dmm-insp-visual-media-type').val() || 'upload',
        tex: 'grain',
        tone: '#3b2416',
        image_url: $('#dmm-insp-visual-image-url').val().trim(),
        kicker: $('#dmm-insp-visual-kicker').val().trim(),
        headline: $('#dmm-insp-visual-headline').val().trim(),
        cta_text: $('#dmm-insp-visual-cta-text').val().trim(),
        cta_url: $('#dmm-insp-visual-cta-url').val().trim()
      };

      item.feature = {
        enabled: $('#dmm-insp-feature-enabled').is(':checked'),
        media_type: $('#dmm-insp-feature-media-type').val() || 'upload',
        tex: 'smooth',
        tone: '#3b2416',
        image_url: $('#dmm-insp-feature-image-url').val().trim(),
        kicker: $('#dmm-insp-feature-kicker').val().trim(),
        text: $('#dmm-insp-feature-text').val().trim(),
        cta_text: $('#dmm-insp-feature-cta-text').val().trim(),
        cta_url: $('#dmm-insp-feature-cta-url').val().trim()
      };

      this.closeInspector();
      this.renderTree();
      this.showToast('Item "' + item.title + '" updated', false);
    },

    /**
     * Delete Confirmation Modal.
     */
    initDeleteModal: function () {
      var self = this;
      var $modal = $('#dmm-delete-modal');

      $modal.on('click', '#dmm-modal-cancel-btn, #dmm-modal-abort-btn', function (e) {
        e.preventDefault();
        $modal.fadeOut(120);
        self.pendingDelete = null;
      });

      $modal.on('click', '#dmm-modal-confirm-btn', function (e) {
        e.preventDefault();
        if (self.pendingDelete) {
          self.deleteItem(self.pendingDelete);
          $modal.fadeOut(120);
          self.pendingDelete = null;
        }
      });
    },

    openDeleteModal: function (id, title) {
      this.pendingDelete = id;
      $('#dmm-modal-target-title').text(title || '(No Title)');
      $('#dmm-delete-modal').fadeIn(120);
    },

    deleteItem: function (id) {
      var item = this.findItem(id);
      if (!item) return;

      var parentId = item.menu_item_parent || '0';

      for (var i = 0; i < this.data.length; i++) {
        if (String(this.data[i].menu_item_parent) === String(id)) {
          this.data[i].menu_item_parent = parentId;
        }
      }

      this.data = this.data.filter(function (it) {
        return String(it.db_id) !== String(id);
      });

      delete this.collapsed[String(id)];
      this.renderTree();
      this.showToast('Item deleted successfully', false);
    },

    /**
     * Raw JSON Panel bindings.
     */
    bindJsonPanel: function () {
      var self = this;

      $('.dmm-btn-toggle-json').on('click', function (e) {
        e.preventDefault();
        var $panel = $('#dmm-json-panel-main');
        $panel.slideToggle(180);
        if ($panel.is(':visible')) {
          self.updateJsonTextarea();
        }
      });

      $('.dmm-btn-close-json').on('click', function (e) {
        e.preventDefault();
        $('#dmm-json-panel-main').slideUp(180);
      });

      $('.dmm-btn-apply-json').on('click', function (e) {
        e.preventDefault();
        try {
          var raw = $('#dmm-json-textarea-main').val();
          var parsed = JSON.parse(raw);
          if (Array.isArray(parsed)) {
            self.data = parsed;
            self.renderTree();
            self.showToast('JSON schema loaded into tree successfully', false);
            $('#dmm-json-panel-main').slideUp(180);
          } else {
            self.showToast('Error: JSON must be an array of menu items.', true);
          }
        } catch (err) {
          self.showToast('Invalid JSON: ' + err.message, true);
        }
      });

      $('.dmm-btn-copy-json').on('click', function (e) {
        e.preventDefault();
        var textarea = document.getElementById('dmm-json-textarea-main');
        textarea.select();
        document.execCommand('copy');
        self.showToast('JSON copied to clipboard!', false);
      });
    },

    updateJsonTextarea: function () {
      $('#dmm-json-textarea-main').val(JSON.stringify(this.data || [], null, 2));
    },

    /**
     * Import WP Menu & Reset Presets.
     */
    bindImportAndReset: function () {
      var self = this;

      $('.dmm-btn-import-wp').on('click', function (e) {
        e.preventDefault();
        var menuId = $('#dmm-import-select-main').val();
        if (!menuId) {
          self.showToast('Please select a WordPress menu to import.', true);
          return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Importing...');

        $.ajax({
          url: (window.dmmAdmin ? window.dmmAdmin.ajaxUrl : '') || ajaxurl,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'dmm_import_wp_menu',
            nonce: (window.dmmAdmin ? window.dmmAdmin.nonce : ''),
            menu_id: menuId,
            device: 'desktop'
          },
          success: function (res) {
            $btn.prop('disabled', false).text('Import');
            if (res && res.success && res.data && res.data.items) {
              self.data = res.data.items;
              self.renderTree();
              self.showToast('Imported ' + res.data.items.length + ' items from WordPress menu!', false);
            } else {
              self.showToast((res && res.data && res.data.message) ? res.data.message : 'Import failed', true);
            }
          },
          error: function () {
            $btn.prop('disabled', false).text('Import');
            self.showToast('Network error during menu import.', true);
          }
        });
      });

      $('.dmm-btn-reset-sample').on('click', function (e) {
        e.preventDefault();
        if (!confirm('Restore default Handdn luxury presets? Any unsaved changes will be replaced.')) {
          return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
          url: (window.dmmAdmin ? window.dmmAdmin.ajaxUrl : '') || ajaxurl,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'dmm_reset_sample_data',
            nonce: (window.dmmAdmin ? window.dmmAdmin.nonce : ''),
            device: 'desktop'
          },
          success: function (res) {
            $btn.prop('disabled', false);
            if (res && res.success && res.data && res.data.items) {
              self.data = res.data.items;
              self.renderTree();
              self.showToast('Successfully restored luxury Handdn presets!', false);
            } else {
              self.showToast('Reset failed.', true);
            }
          },
          error: function () {
            $btn.prop('disabled', false);
            self.showToast('Network error during reset.', true);
          }
        });
      });
    },

    /**
     * Collapse / Expand All Toggles.
     */
    bindCollapseToggles: function () {
      var self = this;

      $('.dmm-btn-collapse-all').on('click', function (e) {
        e.preventDefault();
        self.collapsed = {};
        for (var i = 0; i < self.data.length; i++) {
          if (self.countDirectChildren(self.data[i].db_id) > 0) {
            self.collapsed[String(self.data[i].db_id)] = true;
          }
        }
        self.renderTree();
      });

      $('.dmm-btn-expand-all').on('click', function (e) {
        e.preventDefault();
        self.collapsed = {};
        self.renderTree();
      });
    },

    /**
     * Global Save Changes.
     */
    bindGlobalSave: function () {
      var self = this;

      $('#dmm-global-save-btn').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dmm-btn-label').text('Saving...');

        var rootPromo = {
          enabled: $('#dmm-promo-enabled').is(':checked'),
          media_type: $('#dmm-promo-media-type').val() || 'upload',
          tex: 'grain',
          tone: '#8a4a26',
          image_url: $('#dmm-promo-image-url').val().trim(),
          kicker: $('#dmm-promo-kicker').val().trim(),
          headline: $('#dmm-promo-headline').val().trim(),
          cta_text: $('#dmm-promo-cta-text').val().trim(),
          cta_url: $('#dmm-promo-cta-url').val().trim(),
          show_strap_svg: $('#dmm-promo-svg').is(':checked')
        };

        var drawerFooter = {
          trustpilot_enabled: $('#dmm-tp-enabled').is(':checked'),
          trustpilot_auto: $('#dmm-tp-auto').is(':checked'),
          trustpilot_rating: $('#dmm-tp-rating').val().trim(),
          trustpilot_count: $('#dmm-tp-count').val().trim(),
          trustpilot_url: $('#dmm-tp-url').val().trim(),
          account_link_enabled: $('#dmm-foot-account').is(':checked'),
          track_order_enabled: $('#dmm-foot-track').is(':checked'),
          contact_link_enabled: $('#dmm-foot-contact').is(':checked'),
          instagram_url: $('#dmm-foot-instagram').val().trim()
        };

        var generalSettings = {
          enable_plugin: $('#dmm-master-toggle').is(':checked') ? 1 : 0,
          mobile_breakpoint: parseInt($('#dmm-mobile-breakpoint').val(), 10) || 767,
          desktop_override: 1,
          mobile_override: 1,
          desktop_source: 'custom_json',
          mobile_source: 'custom_json'
        };

        $.ajax({
          url: (window.dmmAdmin ? window.dmmAdmin.ajaxUrl : '') || ajaxurl,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'dmm_save_all_settings',
            nonce: (window.dmmAdmin ? window.dmmAdmin.nonce : ''),
            menu_data: JSON.stringify(self.data || []),
            root_promo: JSON.stringify(rootPromo),
            drawer_footer: JSON.stringify(drawerFooter),
            general_settings: JSON.stringify(generalSettings)
          },
          success: function (res) {
            $btn.prop('disabled', false).find('.dmm-btn-label').text('Save Changes');
            if (res && res.success) {
              self.showToast('All changes saved successfully!', false);
            } else {
              self.showToast((res && res.data && res.data.message) ? res.data.message : 'Save failed', true);
            }
          },
          error: function () {
            $btn.prop('disabled', false).find('.dmm-btn-label').text('Save Changes');
            self.showToast('Network error while saving settings.', true);
          }
        });
      });
    }
  };

  $(document).ready(function () {
    DMM.init();
  });

})(jQuery);
