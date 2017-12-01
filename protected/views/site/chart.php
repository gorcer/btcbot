<?php
/* @var $this SiteController */

$this->pageTitle=Yii::app()->name;


$flags=", {
		        type: 'flags',
		        name: 'Flags on series',
		        data: [";
foreach ($buys as $buy)
{
	
	$name = 'b';
	$color='';
	if ($buy->sold>0)
	{
		$name='<s>-b</s>';
		$color="color: '#aaffaa',";
	}
	
	$flags.="{
					x: ".(strtotime($buy->dtm)*1000+4*60*60*1000).",
					title: '".$name."',
					".$color."
					events: {
							click: function () {
								document.location.href = '".Yii::app()->createUrl('site/chartByTrack', array('dt'=>$buy->dtm))."'										
								}
							}
				}, ";
}

foreach ($sells as $sell)
{

	$name = 's';
	

	$flags.="{
					x: ".(strtotime($sell->dtm)*1000+4*60*60*1000).",
					title: '".$name."',
					color: '#ffaaaa',
					events: {
							click: function () {
								document.location.href = '".Yii::app()->createUrl('site/chartByTrack', array('dt'=>$sell->dtm))."'
								}
							}
				}, ";
}

$flags.="],
		        onSeries: 'dataseries',
		        shape: 'squarepin'
		    }";
?>

<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"></script>
<script src="http://code.highcharts.com/stock/highstock.js"></script>

<div id="container" style="height: 500px; min-width: 500px"></div>

Баланс ($): <?php echo $status['balance']; ?><br/>
Баланс (btc): <?php echo $status['balance_btc']; ?><br/>
Заработано ($): <?php echo $status['total_income']; ?><br/>
Общие активы (руб.): <?php echo $status['total_balance']; ?><br/>
Всего вложено (руб.): <?php echo $status['start_balance']; ?><br/>
<br/>
<h2>Последние сделки</h2>
<table>
<?php foreach ($orders as $order): ?>
<tr>
 <td><?php echo CHtml::link($order->id, array('site/viewOrder', 'id'=>$order->id )); ?></td>
    <td><?php  echo $order->create_dtm; ?></td>
 <td><?php  echo $order->type; ?></td>
 <td><?php  echo $order->price; ?> Х <?php  echo $order->count; ?> =</td>
 <td><?php  echo $order->summ; ?></td>
 <td><?php  echo $order->status; ?></td>
</tr>
<?php endforeach; ?>
</table>

<h2>Причины не покупки</h2>
<?php 
$i=0;
for ($i=0;$i<5;$i++)
{
	$dt = strtotime('-'.$i.' days');
	$path='logs/not-buy-'.date('Y-m-d', $dt).'.html';	
	echo '<a href="'.$path.'">'.$path.'</a><br/>';
}
?>


<h2>Причины не продажи</h2>
<?php 
$i=0;
for ($i=0;$i<5;$i++)
{
	$dt = strtotime('-'.$i.' days');
	$path='logs/not-sell-'.date('Y-m-d', $dt).'.html';	
	echo '<a href="'.$path.'">'.$path.'</a><br/>';
}
?>


<script>
$(function() {
	
		
		// Create the chart
		$('#container').highcharts('StockChart', {
		    chart: {
		    },

		    rangeSelector: {
		        selected: 1
		    },

		    title: {
		        text: 'BTC-USD'
		    },

		    yAxis: {
		        title: {
		            text: 'Exchange rate'
		        }
		    },

			series: [{
		        name: 'buy',
		        data: <?php echo $data_buy; ?>,
				id: 'dataseries',
				tooltip: {
					valueDecimals: 4
				}
		    }, {
		        name: 'sell',
		        data: <?php echo $data_sell; ?>,
				id: 'dataseries',
				tooltip: {
					valueDecimals: 4
				}
		    }, {
		        name: 'no-data',
		        data: <?php echo $no_data; ?>,
		        lineWidth : 0,
				marker : {
							enabled : true,
							radius : 2
						},
				id: 'dataseries',
				color: '#ff3333',
				tooltip: {
					valueDecimals: 4
				}
		    }
		     <?php echo $flags; ?>]
		    
		});
	
});

</script>