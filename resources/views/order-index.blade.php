<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @vite('resources/js/app.js')
</head>

<body>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <ul>
                    <li><a href="/">Order</a></li>
                    <li><a href="/food">Food</a></li>
                    <li><a href="/orders">Orders</a></li>
                </ul>
                <div class="row" id="orderList">
                    <div class="col-4">
                        <h1>Orders</h1>
                        <ul id="pendingOrders">
                            @foreach ($models->where('status', 0) as $model)
                            <li data-id="{{ $model->id }}">
                                Order N{{ $model->id }}<br>
                                @foreach ($model->orderItems as $item)
                                - {{ $item->foods->name }}<br>
                                @endforeach
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-4">
                        <h1>Accepted</h1>
                        <ul id="acceptedOrders">
                            @foreach ($models->where('status', 1) as $model)
                            <li data-id="{{ $model->id }}">
                                Order N{{ $model->id }}<br>
                                @foreach ($model->orderItems as $item)
                                - {{ $item->foods->name }}<br>
                                @endforeach
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-4">
                        <h1>Rejected</h1>
                        <ul id="rejectedOrders">
                            @foreach ($models->where('status', 2) as $model)
                            <li data-id="{{ $model->id }}">
                                Order N{{ $model->id }}<br>
                                @foreach ($model->orderItems as $item)
                                - {{ $item->foods->name }}<br>
                                @endforeach
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>