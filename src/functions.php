<?php

function get_bikes($conn) {
    return mysqli_query($conn, "SELECT * FROM bikes");
}

function add_bike($conn, $code) {
    return mysqli_query($conn, "INSERT INTO bikes (code) VALUES('$code')");
}

function start_rental($conn, $customer_id, $bike_id) {
    mysqli_query($conn, "INSERT INTO rentals (customer_id,bike_id,start_time,status)
                         VALUES ($customer_id,$bike_id,NOW(),'ongoing')");
    mysqli_query($conn, "UPDATE bikes SET status='rented' WHERE id=$bike_id");
}

function end_rental($conn, $rental_id) {
    mysqli_query($conn, "UPDATE rentals 
                         SET end_time = NOW(), status = 'completed' 
                         WHERE id=$rental_id");
}
