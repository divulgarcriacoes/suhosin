(function ($) {
	'use strict';

	if (typeof dcpriceVars === 'undefined') {
		return;
	}

	// 26/11/2026: o campo do lado do preço agora é a QUANTIDADE EM UNIDADES (o cliente digita 46 copos),
	// não mais "pacotes" multiplicando a faixa marcada. A faixa da tabela acompanha a quantidade digitada
	// (a maior faixa até essa quantidade) e clicar numa faixa preenche a quantidade dela.

	function fmtBRL(value) {
		return 'R$ ' + Number(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function fmtInt(value) {
		return Number(value).toLocaleString('pt-BR');
	}

	function nativeQtyInput($box) {
		return $box.closest('form.cart, .product, .summary').find('input.qty[name="quantity"]').first();
	}

	function technique($box) {
		return $box.find('input[name="dcprice_technique"]:checked').val() || dcpriceVars.defaultTechnique || '';
	}

	function tiers($box) {
		var t = [];
		$box.find('input[name="dcprice_qty"]').each(function () {
			var v = parseInt($(this).val(), 10);
			if (v > 0) {
				t.push(v);
			}
		});
		return t.sort(function (a, b) { return a - b; });
	}

	function minQty($box) {
		var t = tiers($box);
		return t.length ? t[0] : 1;
	}

	function qty($box) {
		var v = parseInt($box.find('.dcprice-step-value').val(), 10);
		return v > 0 ? v : 0;
	}

	// maior faixa que cabe na quantidade (abaixo do mínimo: a primeira faixa)
	function tierFor($box, q) {
		var t = tiers($box), best = t.length ? t[0] : 0;
		for (var i = 0; i < t.length; i++) {
			if (t[i] <= q) {
				best = t[i];
			}
		}
		return best;
	}

	function refresh($box) {
		var tech = technique($box);
		var mat = dcpriceVars.matrix[tech] || {};
		var q = qty($box);
		var min = minQty($box);

		// tabela: preço de cada faixa
		$box.find('.dcprice-cell-unit, .dcprice-cell-total').each(function () {
			var $cell = $(this);
			var cellRow = mat[$cell.data('qty')];
			if (!cellRow) {
				$cell.text('—');
				return;
			}
			$cell.text($cell.hasClass('dcprice-cell-unit') ? fmtBRL(cellRow.unit_price) : fmtBRL(cellRow.total));
		});

		var tq = tierFor($box, q);
		$box.find('input[name="dcprice_qty"]').each(function () {
			this.checked = parseInt($(this).val(), 10) === tq;
		});
		var row = mat[tq];
		var $note = $box.find('.dcprice-total-note');
		if (row && q >= min) {
			$box.find('.dcprice-big-total').text(fmtBRL(row.unit_price * q));
			$note.css('color', '').text(fmtInt(q) + ' unidades · ' + fmtBRL(row.unit_price) + ' cada');
		} else if (row) {
			$box.find('.dcprice-big-total').text(fmtBRL(row.unit_price * min));
			$note.css('color', '#C0392B').text('Pedido mínimo: ' + fmtInt(min) + ' unidades');
		}

		var $native = nativeQtyInput($box);
		if ($native.length) {
			$native.val(Math.max(q, min)).trigger('change');
		}

		$box.find('.dcprice-techniques label span:first-child').css('outline', 'none');
		$box.find('input[name="dcprice_technique"]:checked').closest('label').find('span:first-child').css('outline', '2px solid #F5821F');

		// texto da técnica: troca SEMPRE (se a técnica não tem descrição, limpa)
		var desc = $box.find('input[name="dcprice_technique"]:checked').data('description');
		$box.find('.dcprice-technique-desc').text(desc || '');
	}

	function relocateNativeControls($box) {
		var $form = $box.closest('form.cart');
		if (!$form.length) {
			return;
		}
		// esconde o campo de quantidade nativo (o campo da caixa já controla o valor real do formulário)
		$form.find('.quantity').not($box.find('.quantity')).hide();

		// move o botão "Adicionar ao carrinho" pra dentro da caixa
		var $btn = $form.find('button.single_add_to_cart_button, button[type="submit"]').first();
		if ($btn.length && !$box.find('.dcprice-addtocart-slot').find($btn).length) {
			$btn.css({ 'white-space': 'nowrap' });
			$box.find('.dcprice-addtocart-slot').append($btn);
		}

		// o tema mostra "X em estoque" duplicado — deixa só um, abaixo do botão
		var $stockAll = $('.stock');
		if ($stockAll.length > 1) {
			$stockAll.slice(1).hide();
		}
		var $stock = $stockAll.first();
		if ($stock.length && !$box.find('.dcprice-stock-slot').find($stock).length) {
			$stock.css({ margin: '6px 0 0' });
			$box.find('.dcprice-stock-slot').append($stock);
		}

		// abaixo do mínimo não deixa enviar: corrige para o mínimo
		$form.on('submit', function () {
			var min = minQty($box);
			if (qty($box) < min) {
				$box.find('.dcprice-step-value').val(min);
				refresh($box);
			}
		});
	}

	function bindBox($box) {
		var $in = $box.find('.dcprice-step-value');
		// rótulo e campo em unidades
		$box.find('.dcprice-pack-size').parent().html('Quantidade<br />(unidades)');
		$in.attr({ min: minQty($box), step: 1, inputmode: 'numeric' });
		if ($in[0]) {
			$in[0].style.setProperty('width', '64px', 'important');
			$in[0].style.setProperty('border', '0', 'important');
			$in[0].style.setProperty('box-shadow', 'none', 'important');
		}
		var start = parseInt($box.find('input[name="dcprice_qty"]:checked').val(), 10) || minQty($box);
		$in.val(start);

		relocateNativeControls($box);

		$box.on('change', 'input[name="dcprice_technique"]', function () {
			refresh($box);
		});

		// clicar numa faixa preenche a quantidade dela
		$box.on('change', 'input[name="dcprice_qty"]', function () {
			$in.val(parseInt($(this).val(), 10));
			refresh($box);
		});

		$box.on('input', '.dcprice-step-value', function () {
			refresh($box);
		});
		$box.on('change blur', '.dcprice-step-value', function () {
			var min = minQty($box);
			if (qty($box) < min) {
				$(this).val(min);
			}
			refresh($box);
		});

		$box.on('click', '.dcprice-step-minus, .dcprice-step-plus', function () {
			var min = minQty($box);
			var v = qty($box) || min;
			v = $(this).hasClass('dcprice-step-minus') ? Math.max(min, v - 1) : v + 1;
			$in.val(v);
			refresh($box);
		});

		refresh($box);
	}

	$(function () {
		$('.dcprice-box').each(function () {
			bindBox($(this));
		});
	});
})(jQuery);
