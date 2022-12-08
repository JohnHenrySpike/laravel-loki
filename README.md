# laravel-loki

Loki.php is helper class for sending requests to Grafana Loki https://github.com/grafana/loki

Example usage:

```
$loki = new Loki();
$res = $loki->setStart($start)->setEnd($end)->get(
    'sum(
    sum_over_time(
    {job="pushpull"}
    | json
    | error!="JSONParserErr"
    | message="User disconnected"
    | unwrap onlineTime [$__range]
    )
    ) by (date)'
);
```
