<?php
/**
 *
 * @About:      Database manager
 * @File:       Database.php
 * @Date:       28022023
 * @Version:    0.1
 **/
class DbConnect {
 
    private $conn;
 
    public function connect() {
        require __DIR__  . '/config.php';
        
            $this->conn = mysqli_connect($DATABASE_HOST, $DATABASE_USER, $DATABASE_PASS, $DATABASE_NAME);

            if ( mysqli_connect_errno() ) {
                // If there is an error with the connection, stop the script and display the error.
                exit("Failed to connect to database");
            }
            // returing connection resource
            return $this->conn;
    }
 
}
?>

