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
                <h1>Employees</h1>
                <form action="/employee" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-check">
                        @foreach ($models as $model)
                        <div class="mb-2">
                            <input class="form-check-input"
                                type="checkbox"
                                value="{{$model->id}}"
                                id="flexCheck{{$model->id}}"
                                name="employees[]">
                            <label class="form-check-label" for="flexCheck{{$model->id}}">
                                {{$model->name}}
                            </label>
                        </div>
                        @endforeach
                    </div>
                    <input type="file" name="file" class="form-control">
                    <button type="submit" class="btn btn-primary mt-3">Submit</button>
                </form>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>