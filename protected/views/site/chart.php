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
								document.location.href = '".Yii::app()->createUrl('buy/view', array('id'=>$buy->id))."'										
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
								document.location.href = '".Yii::app()->createUrl('sell/view', array('id'=>$sell->id))."'
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

Баланс (руб.): <?php echo $status['balance']; ?><br/>
Баланс (btc): <?php echo $status['balance_btc']; ?><br/>
Заработано (руб.): <?php echo $status['total_income']; ?><br/>
Общие активы (руб.): <?php echo $status['total_balance']; ?><br/>
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
		        text: 'BTC-RUR'
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
		    }
		     <?php echo $flags; ?>]
		    
		});
	
});

</script>