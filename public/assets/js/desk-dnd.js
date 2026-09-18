/**
 * Drag-drop заявок/машин + подсветка заявки на карте.
 */
(function () {
'use strict';
let ddPotential = null;
let ddDrag = null;
let ddGap = null;

function ddGhost(src) {
  const g = src.cloneNode(true);
  const w = src.getBoundingClientRect().width;
  g.classList.add('dd-ghost');
  g.style.position = 'fixed';
  g.style.left = '-9999px';
  g.style.top = '0';
  g.style.width = w + 'px';
  g.style.pointerEvents = 'none';
  document.body.appendChild(g);
  return g;
}
function ddMoveGhost(e) {
  if (!ddDrag.ghost) return;
  const w = ddDrag.ghost.offsetWidth;
  ddDrag.ghost.style.left = (e.clientX - w / 2) + 'px';
  ddDrag.ghost.style.top = (e.clientY - 14) + 'px';
}
function ddClearGap() {
  if (ddGap && ddGap.parentNode) ddGap.parentNode.removeChild(ddGap);
  ddGap = null;
}
function ddShowGap(container, beforeEl) {
  ddClearGap();
  ddGap = document.createElement('div');
  ddGap.className = 'dd-gap';
  if (beforeEl) container.insertBefore(ddGap, beforeEl);
  else container.appendChild(ddGap);
}
function ddItems(container) {
  return Array.from(container.querySelectorAll(':scope > .drag-order, :scope > .veh-chip'))
    .filter(function (it) { return !it.classList.contains('dd-source'); });
}
function ddInsertBefore(items, y) {
  for (var i = 0; i < items.length; i++) {
    var r = items[i].getBoundingClientRect();
    if (y < r.top + r.height / 2) return items[i];
  }
  return null;
}
function ddDropTarget(e) {
  const under = document.elementFromPoint(e.clientX, e.clientY);
  if (!under) return null;
  if (ddDrag.isOrder) {
    const trip = under.closest('.trip');
    if (trip) {
      const container = trip.querySelector('.orders-list');
      return { kind: 'trip', tripId: parseInt(trip.getAttribute('data-trip-id'), 10), container: container || trip };
    }
    const un = under.closest('#unassignedZone');
    if (un) return { kind: 'un', container: un };
    return null;
  }
  const zone = under.closest('[data-zone-drop]');
  if (zone) {
    const container = zone.querySelector('.zone-vehicles');
    return { kind: 'zone', zoneId: parseInt(zone.getAttribute('data-zone-drop'), 10), container: container || zone };
  }
  return null;
}
function ddStart(el, isOrder, e) {
  ddDrag = {
    isOrder: isOrder,
    el: el,
    info: isOrder
      ? { order_id: parseInt(el.getAttribute('data-order-id'), 10), from_trip: el.getAttribute('data-from-trip') || null }
      : { vehicle_id: parseInt(el.getAttribute('data-vehicle-id'), 10), from_zone: parseInt(el.getAttribute('data-zone-id'), 10) },
    ghost: ddGhost(el),
    target: null
  };
  el.classList.add('dd-source');
  document.body.classList.add('dd-dragging');
  ddMoveGhost(e);
}
async function ddFinish(e) {
  const drag = ddDrag;
  ddDrag = null;
  if (!drag) return;
  const t = drag.target;
  const el = drag.el;
  const tContainer = t && t.container;
  const insertBeforeEl = (ddGap && ddGap.parentNode === tContainer) ? ddGap.nextElementSibling : null;
  ddClearGap();
  if (drag.ghost && drag.ghost.parentNode) drag.ghost.parentNode.removeChild(drag.ghost);
  el.classList.remove('dd-source');
  document.body.classList.remove('dd-dragging');
  document.querySelectorAll('.dd-over').forEach(function (n) { n.classList.remove('dd-over'); });
  if (!t) return;
  try {
    await ddApplyMove(drag, t, insertBeforeEl);
  } catch (err) {
    alert(err.message || String(err));
  }
}
async function ddApplyMove(drag, target, insertBeforeEl) {
  var url, params;
  if (drag.isOrder) {
    var oid = drag.info.order_id;
    var fromTrip = drag.info.from_trip;
    if (target.kind === 'un') {
      if (!fromTrip) return;
      url = 'api/trip_order.php';
      params = { action: 'remove', order_id: oid, trip_id: parseInt(fromTrip, 10) };
    } else if (target.kind === 'trip') {
      url = 'api/trip_order.php';
      if (!fromTrip) {
        params = { action: 'add', order_id: oid, to_trip_id: target.tripId };
      } else if (String(fromTrip) === String(target.tripId)) {
        var list = ddBuildOrderList(target.container, oid, insertBeforeEl);
        params = { action: 'reorder', trip_id: target.tripId, order_ids: list, moved_order_id: oid };
      } else {
        params = { action: 'move', order_id: oid, from_trip_id: parseInt(fromTrip, 10), to_trip_id: target.tripId };
      }
    } else return;
  } else {
    if (target.kind !== 'zone') return;
    if (drag.info.from_zone === target.zoneId) return;
    url = 'api/vehicle_zone.php';
    params = { action: 'move', vehicle_id: drag.info.vehicle_id, from_zone_id: drag.info.from_zone, zone_id: target.zoneId };
  }
  const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(params) });
  const data = await r.json();
  if (!data.ok) throw new Error(data.error || 'Ошибка');
  if (window.deskAckLocalChange) window.deskAckLocalChange();
  location.reload();
}
function ddBuildOrderList(container, orderId, insertBeforeEl) {
  // Порядок заявок после сброса: перетаскиваемая ставится на позицию
  // курсора (перед insertBeforeEl — элементом, перед которым был gap),
  // остальные остаются в текущем порядке.
  const all = Array.from(container.querySelectorAll(':scope > .drag-order'));
  const beforeId = insertBeforeEl ? parseInt(insertBeforeEl.getAttribute('data-order-id'), 10) : 0;
  const others = [];
  all.forEach(function (o) {
    const id = parseInt(o.getAttribute('data-order-id'), 10);
    if (id && id !== orderId) others.push(id);
  });
  if (beforeId && beforeId !== orderId) {
    const i = others.indexOf(beforeId);
    if (i >= 0) others.splice(i, 0, orderId); else others.push(orderId);
  } else {
    others.push(orderId);
  }
  return others;
}

document.addEventListener('mousedown', function (e) {
  if (e.button !== 0) return;
  if (e.target.closest('button, a, input, select')) return;
  const ord = e.target.closest('.drag-order');
  const veh = e.target.closest('.veh-chip');
  if (ord) {
    e.preventDefault();
    ddPotential = { el: ord, isOrder: true, x: e.clientX, y: e.clientY };
  } else if (veh) {
    e.preventDefault();
    ddPotential = { el: veh, isOrder: false, x: e.clientX, y: e.clientY };
  }
});
document.addEventListener('mousemove', function (e) {
  if (ddPotential && !ddDrag) {
    var dx = e.clientX - ddPotential.x, dy = e.clientY - ddPotential.y;
    if (dx * dx + dy * dy > 36) {
      ddStart(ddPotential.el, ddPotential.isOrder, e);
      ddPotential = null;
    }
    return;
  }
  if (!ddDrag) return;
  ddMoveGhost(e);
  var t = ddDropTarget(e);
  document.querySelectorAll('.dd-over').forEach(function (n) { n.classList.remove('dd-over'); });
  ddDrag.target = t;
  if (t && t.container) {
    t.container.classList.add('dd-over');
    if (ddDrag.isOrder && (t.kind === 'trip' || t.kind === 'un')) {
      var items = ddItems(t.container);
      var before = ddInsertBefore(items, e.clientY);
      ddShowGap(t.container, before);
    }
  } else {
    ddClearGap();
  }
});
document.addEventListener('mouseup', function (e) {
  ddPotential = null;
  if (!ddDrag) return;
  ddFinish(e);
});

function highlightOrder(id, opts) {
  opts = opts || {};
  document.querySelectorAll('.drag-order').forEach(function (c) {
    c.style.outline = '';
    c.style.outlineOffset = '';
  });
  var marks = window.orderMarks || {};
  Object.keys(marks).forEach(function (k) {
    var m = marks[k];
    if (m && m.__origPreset) {
      try { m.options.set('preset', m.__origPreset); } catch (e) {}
    }
  });
  var card = document.querySelector('.drag-order[data-order-id="' + id + '"]');
  if (card) {
    card.style.outline = '2px solid #f59e0b';
    card.style.outlineOffset = '-2px';
    if (opts.scroll !== false) {
      try { card.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch (e) {}
    }
  }
  var m = marks[id] || marks[String(id)];
  if (m) {
    try {
      if (window.__logisticsMap) window.__logisticsMap.panTo(m.geometry.getCoordinates(), { checkZoomRange: true, delay: 0 });
    } catch (e) {}
    try { m.options.set('preset', 'islands#redCircleDotIcon'); } catch (e) {}
  }
}

function bindOrderClicks() {
  document.querySelectorAll('.drag-order').forEach(function (c) {
    if (c.getAttribute('data-hl-bound')) return;
    c.setAttribute('data-hl-bound', '1');
    c.addEventListener('click', function () {
      if (document.body.classList.contains('dd-dragging')) return;
      highlightOrder(parseInt(c.getAttribute('data-order-id'), 10));
    });
  });
}
bindOrderClicks();
setTimeout(bindOrderClicks, 500);

window.highlightOrder = highlightOrder;
if (!window.orderMarks) window.orderMarks = {};
})();
