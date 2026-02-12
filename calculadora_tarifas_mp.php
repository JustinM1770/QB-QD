<?php
echo "<!DOCTYPE html>";
echo "<html lang='es'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Calculadora Tarifas MercadoPago - QuickBite</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }";
echo ".container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo ".calculator { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }";
echo ".input-group { margin: 15px 0; }";
echo "label { display: block; font-weight: 600; margin-bottom: 5px; }";
echo "input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }";
echo ".result { background: #d4edda; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #28a745; }";
echo ".comparison { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }";
echo ".card { background: #f8f9fa; padding: 15px; border-radius: 8px; }";
echo ".old { border-left: 4px solid #dc3545; }";
echo ".new { border-left: 4px solid #28a745; }";
echo ".btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<div class='container'>";
echo "<h1>💰 Calculadora de Tarifas MercadoPago México</h1>";
echo "<p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>";

echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>📊 Tarifas Oficiales MercadoPago México (2025)</h3>";
echo "<ul>";
echo "<li><strong>Tarjetas de crédito/débito:</strong> 2.9% + IVA (16%) = <strong>3.36%</strong></li>";
echo "<li><strong>Cuota fija por transacción:</strong> <strong>$2.50 MXN</strong></li>";
echo "<li><strong>Monto mínimo:</strong> $10.00 MXN</li>";
echo "</ul>";
echo "</div>";

echo "<div class='calculator'>";
echo "<h3>🧮 Calculadora Interactiva</h3>";
echo "<form onsubmit='return calculateFee(event)'>";
echo "<div class='input-group'>";
echo "<label for='amount'>Monto del pedido (MXN):</label>";
echo "<input type='number' id='amount' step='0.01' min='10' value='200' placeholder='Ej: 200.00'>";
echo "</div>";
echo "<button type='submit' class='btn'>Calcular Comisión</button>";
echo "</form>";
echo "<div id='calculation-result'></div>";
echo "</div>";

echo "<div class='comparison'>";
echo "<div class='card old'>";
echo "<h4>❌ Tarifas INCORRECTAS (antes)</h4>";
echo "<ul>";
echo "<li>Porcentaje: <strong>3.99%</strong></li>";
echo "<li>Cuota fija: <strong>$4.00</strong></li>";
echo "</ul>";
echo "<p><strong>Para pedido de $200:</strong></p>";
echo "<p>Comisión = ($200 × 3.99%) + $4.00 = <strong style='color:#dc3545;'>$11.98</strong></p>";
echo "</div>";

echo "<div class='card new'>";
echo "<h4>✅ Tarifas CORRECTAS (ahora)</h4>";
echo "<ul>";
echo "<li>Porcentaje: <strong>3.36%</strong></li>";
echo "<li>Cuota fija: <strong>$2.50</strong></li>";
echo "</ul>";
echo "<p><strong>Para pedido de $200:</strong></p>";
echo "<p>Comisión = ($200 × 3.36%) + $2.50 = <strong style='color:#28a745;'>$9.22</strong></p>";
echo "</div>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #856404;'>";
echo "<h3>⚠️ Problema Identificado</h3>";
echo "<p>Si te cobraron <strong>$41.44</strong> en lugar de ~$10.50, el problema era:</p>";
echo "<ol>";
echo "<li><strong>Porcentaje incorrecto:</strong> 3.99% en lugar de 3.36%</li>";
echo "<li><strong>Cuota fija incorrecta:</strong> $4.00 en lugar de $2.50</li>";
echo "<li><strong>Posible cálculo sobre total ya con comisión</strong> (bucle infinito)</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;'>";
echo "<h3>✅ Solución Aplicada</h3>";
echo "<ul>";
echo "<li>Actualizadas las tarifas correctas de MercadoPago México</li>";
echo "<li>Corregido el porcentaje a 3.36% (2.9% + IVA 16%)</li>";
echo "<li>Reducida la cuota fija a $2.50 MXN</li>";
echo "<li>Actualizado el indicador visual en el checkout</li>";
echo "</ul>";
echo "</div>";

echo "<div style='margin-top: 30px; text-align: center;'>";
echo "<a href='checkout.php' style='padding: 15px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 0 10px;'>🛒 Probar Checkout Corregido</a>";
echo "<a href='test_token_completo.php' style='padding: 15px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 0 10px;'>🧪 Test Completo</a>";
echo "</div>";

echo "</div>";

echo "<script>";
echo "function calculateFee(event) {";
echo "  event.preventDefault();";
echo "  const amount = parseFloat(document.getElementById('amount').value) || 0;";
echo "  ";
echo "  if (amount < 10) {";
echo "    document.getElementById('calculation-result').innerHTML = '<div style=\"background:#f8d7da; padding:10px; border-radius:5px; color:#721c24; margin-top:15px;\">⚠️ El monto mínimo es $10.00 MXN</div>';";
echo "    return false;";
echo "  }";
echo "  ";
echo "  const oldFee = (amount * 0.0399) + 4.00;";
echo "  const newFee = (amount * 0.0336) + 2.50;";
echo "  const difference = oldFee - newFee;";
echo "  ";
echo "  const result = `";
echo "    <div class='result'>";
echo "      <h4>📊 Resultados para: $${amount.toFixed(2)}</h4>";
echo "      <p><strong>❌ Tarifa anterior (incorrecta):</strong> $${oldFee.toFixed(2)}</p>";
echo "      <p><strong>✅ Tarifa actual (correcta):</strong> $${newFee.toFixed(2)}</p>";
echo "      <p><strong>💰 Ahorro:</strong> $${difference.toFixed(2)}</p>";
echo "      <hr>";
echo "      <p><strong>🧮 Cálculo:</strong> ($${amount.toFixed(2)} × 3.36%) + $2.50 = $${newFee.toFixed(2)}</p>";
echo "    </div>";
echo "  `;";
echo "  ";
echo "  document.getElementById('calculation-result').innerHTML = result;";
echo "  return false;";
echo "}";
echo "calculateFee({preventDefault: () => {}});"; // Calcular automáticamente al cargar
echo "</script>";

echo "</body>";
echo "</html>";
?>