<!DOCTYPE html>
<html lang="ro">

<head>

    <meta charset="UTF-8">

    <style>

        @include('invoices.style')

    </style>

</head>

<body>

@include('invoices.header')

@include('invoices.customer')

@include('invoices.items')

@include('invoices.totals')

@include('invoices.footer')

</body>

</html>