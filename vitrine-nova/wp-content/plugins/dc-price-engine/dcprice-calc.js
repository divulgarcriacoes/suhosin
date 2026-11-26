(function ($) {
	'use strict';

	if (typeof dcpriceVars === 'undefined') {
		return;
	}

	function fmtBRL(value) {
		return 'R$ ' + Number(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function fmtInt(value) {
		return Number(value).toLocaleString('pt-BR');
	}

	function nativeQtyInput($box) {
		return $box.closest('form.cart, .product, .summary').find('input.qty[name="quantity"]').first();
	}

	function packMultiplier($box) {
		var v = parseInt($box.find('.dcprice-step-value').val(), 10);
		return v > 0 ? v : 1;
	}

	function refresh($box) {
		// sem seletor de técnica na tela (só existe 1 opção pra esse produto)
		// -> usa a técnica padrão que o PHP mandou, em vez de nada.
		var technique = $box.find('input[name="dcprice_technique"]:checked').val() || dcpriceVars.defaultTechnique || '';
		var packQty = $box.find('input[name="dcprice_qty"]:checked').val();
		var mult = packMultiplier($box);
		var row = (dcpriceVars.matrix[technique] || {})[packQty];

		// tabela: sempre mostra o preço de UM pacote (não multiplica aqui)
		$box.find('.dcprice-cell-unit, .dcprice-cell-total').each(function () {
			var $cell = $(this);
			var cellQty = $cell.data('qty');
			var cellRow = (dcpriceVars.matrix[technique] || {})[cellQty];
			if (!cellRow) {
				$cell.text('—');
				return;
			}
			$cell.text($cell.hasClass('dcprice-cell-unit') ? fmtBRL(cellRow.unit_price) : fmtBRL(cellRow.total));
		});

		if (row) {
			var realQty = parseInt(packQty, 10) * mult;
			var realTotal = row.total * mult;
			$box.find('.dcprice-big-total').text(fmtBRL(realTotal));
			$box.find('.dcprice-total-note').text(
				mult > 1 ? (mult + ' × ' + fmtInt(packQty) + ' un. = ' + fmtInt(realQty) + ' unidades') : fmtInt(realQty) + ' unidades'
			);
			$box.find('.dcprice-pack-size').text(fmtInt(packQty));

			var $native = nativeQtyInput($box);
			if ($native.length) {
				$native.val(realQty).trigger('change');
			}
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
		// esconde o campo de quantidade nativo (redundante — o stepper da
		// caixa já controla o valor real que vai no formulário)
		$form.find('.quantity').not($box.find('.quantity')).hide();

		// move o botão "Adicionar ao carrinho" pra dentro da caixa, do lado
		// do preço e do stepper, num alinhamento só
		var $btn = $form.find('button.single_add_to_cart_button, button[type="submit"]').first();
		if ($btn.length && !$box.find('.dcprice-addtocart-slot').find($btn).length) {
			$btn.css({ 'white-space': 'nowrap' });
			$box.find('.dcprice-addtocart-slot').append($btn);
		}

		// o tema mostra "X em estoque" duplicado na página — deixa só um,
		// logo abaixo do botão, dentro da caixa.
		var $stockAll = $('.stock');
		if ($stockAll.length > 1) {
			$stockAll.slice(1).hide();
		}
		var $stock = $stockAll.first();
		if ($stock.length && !$box.find('.dcprice-stock-slot').find($stock).length) {
			$stock.css({ margin: '6px 0 0' });
			$box.find('.dcprice-stock-slot').append($stock);
		}
	}

	function bindBox($box) {
		relocateNativeControls($box);

		$box.on('change', 'input[name="dcprice_technique"], input[name="dcprice_qty"]', function () {
			refresh($box);
		});

		$box.on('change input', '.dcprice-step-value', function () {
			var v = parseInt($(this).val(), 10);
			if (!v || v < 1) {
				$(this).val(1);
			}
			refresh($box);
		});

		$box.on('click', '.dcprice-step-minus, .dcprice-step-plus', function () {
			var $input = $box.find('.dcprice-step-value');
			var v = packMultiplier($box);
			v = $(this).hasClass('dcprice-step-minus') ? Math.max(1, v - 1) : v + 1;
			$input.val(v);
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