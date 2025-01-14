<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bootstrap Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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
                <h1>Employees</h1>
                <form action="/employee" method="POST" enctype="multipart/form-data">
                    @csrf
                    <select name="user_id" class="form-control">
                        <option value="">Select delieverer</option>
                        @foreach ($models as $model)
                            <option value="{{$model->id}}">{{$model->name}}</option>
                        @endforeach
                    </select>

                    <h1>Foods</h1>
                    <div class="form-check">
                        @foreach ($foods as $food)
                        <div class="mb-2">
                            <input class="form-check-input"
                                type="checkbox"
                                value="{{$food->id}}"
                                id="flexCheck{{$food->id}}"
                                name="food[]">
                            <label class="form-check-label" for="flexCheck{{$food->id}}">
                                {{$food->name}}
                            </label>
                        </div>
                        @endforeach
                    </div>

                    <h1>Location</h1>
                    <input type="text" name="latitude" class="form-control mt-2" placeholder="Latitude">
                    <input type="text" name="longtitude" class="form-control" placeholder="Longtitude">
                    <h1>Time</h1>
                    <input type="time" name="time" class="form-control">
                    <button type="submit" class="btn btn-primary mt-3">Submit</button>
                </form>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>