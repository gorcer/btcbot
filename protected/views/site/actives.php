<?php
/**
 * Created by PhpStorm.
 * User: gorcer
 * Date: 11/24/14
 * Time: 5:17 PM
 */
?>

<h2>Продажи ожидающие покупки</h2>
<table>
	<tr>
		<th>№</th>
		<th>Дата</th>
		<th>Сумма</th>
		<th>Цена продажи</th>
		<th>Требуемая цена</th>
		<th>Процент</th>
		<th>Доступно</th>
	</tr>
<?
foreach($sells as $sell) {
?>
	<tr>
		<td><?php echo $sell['id'] ?></td>
		<td><?php echo $sell['dtm'] ?></td>
		<td><?php echo $sell['summ'] ?>$</td>
		<td><?php echo $sell['price'] ?>$</td>
		<td><?php echo $sell['needPrice'] ?>$</td>
		<td><?php echo $sell['percent']*100 ?>%</td>
		<td><?php echo $sell['summ']-$sell['buyed'] ?>$</td>
	</tr>
<?
}
?>
</table>


<h2>Покупки ожидающие продажи</h2>
<table>
	<tr>
		<th>№</th>
		<th>Дата</th>
		<th>Сумма</th>
		<th>Цена покупки</th>
		<th>Требуемая цена</th>
		<th>Процент</th>
		<th>Доступно</th>
	</tr>
	<?
	foreach($buys as $buy) {
		?>
		<tr>
			<td><?php echo $buy['id'] ?></td>
			<td><?php echo $buy['dtm'] ?></td>
			<td><?php echo $buy['summ'] ?>$</td>
			<td><?php echo $buy['price'] ?>$</td>
			<td><?php echo $buy['needPrice'] ?>$</td>
			<td><?php echo $buy['percent']*100 ?>%</td>
			<td><?php echo $buy['count']-$buy['sold'] ?></td>
		</tr>
	<?
	}
	?>
</table>