<?php

try {
    class usersession
    {
        public $conn;
        public $token;
        public $userQuery;
        public $data;
        public $uid;
        // TODO :To ADD a parameterized query and statements to prevent SQL injection in all the queries
        public function __construct($token)
        {
            $this->conn = Database::getConnection();
            $this->token = $token;
            $table = get_config('SessionTable');
            $this->userQuery = "SELECT * FROM `$table` WHERE `token` = '$this->token'";
            $result = $this->conn->query($this->userQuery);
            if ($result->num_rows) {
                $row_DB = $result->fetch_assoc();
                $this->data = $row_DB;
                $this->uid = $row_DB['uid'];
            }
        }
        public static function authenticate($username, $password, $fingerprint = null) //I think returns error(return statement of login)
        {
            $username = user::login($username, $password)['username'];
            if ($username and isset($fingerprint)) {
                $userobj = new user($username);
                $connection = Database::getConnection();
                $ip = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                $token = md5($username . $ip . $userAgent . time() . rand(0, 999));
                $costAmount = ['cost' => 8];
                $fingerprint = password_hash($fingerprint, PASSWORD_BCRYPT, $costAmount);
                $table = get_config('SessionTable');
                $query = "INSERT INTO `$table` (`uid`, `token`, `login_time`, `ip`, `useragent`, `fingerPrintId` , `active`)
                     VALUES ('$userobj->id', '$token', now(), '$ip', '$userAgent', '$fingerprint' , '1');";
                $queryresult = $connection->query($query);
                if ($queryresult) {
                    return $token;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
        //Below function is used in login.php and it is used to check whether the user is valid or not
        // Prevents Cookie Hijacking, Session Hijacking, Session Fixation, Session Expiration
        public static function authorize($token, $fingerprint = null)
        {
            $authSession = new usersession($token);
            if (isset($_SERVER['REMOTE_ADDR']) and isset($_SERVER['HTTP_USER_AGENT'])) {
                if ($authSession->isValid($token) and $authSession->isActive()) {
                    if ($_SERVER['REMOTE_ADDR'] == $authSession->getIP() and $_SERVER['HTTP_USER_AGENT'] == $authSession->getUserAgent()) {
                        if (password_verify($fingerprint, $authSession->getFingerPrintId())) {
                            Session::$user = $authSession->getuser();
                            return $authSession;
                        } else {
                            throw new Exception("FingerPrint JS Doesn't Match");
                        }
                    } else {
                        throw new Exception("User IP and Browser Doesn't Match");
                    }
                } else {
                    Session::unset();
                    throw new Exception("Login Expired, Login Again");
                }
            } else {
                throw new Exception("IP or UserAgent or FingerPrint JS may be NULL");
                return false;
            }
        }
        public function getuser()
        {
            return new user($this->uid);
        }
        public static function isValid()
        {
            $connection = Database::getConnection();
            $token = Session::get('sessionToken');
            $table = get_config('SessionTable');
            $connquery = "SELECT `login_time` FROM `$table` WHERE `token` = '$token'";
            $result = $connection->query($connquery);
            if ($result) {
                $sqldata = mysqli_fetch_row($connection->query($connquery));
                $sqltime = strtotime($sqldata[0]);
                // echo time() . " "  . "sqltime" . $sqltime + 10;
                if (($sqltime + 600) > time()) {
                    return true;
                } else {
                    $sql = "UPDATE $table SET `active` = '0' WHERE `token` = '$token'";
                    $connection->query($sql);
                    return false;
                }
            } else {
                return false;
            }
        }
        public function removeSession()
        {
            if (!$this->conn) {
                $this->conn = Database::getConnection();
            }
            if (isset($this->uid)) {
                $table = get_config('SessionTable');
                $sql = "DELETE FROM `$table` WHERE `uid` = $this->uid";
                return $this->conn->query($sql) ? true : false;
            } else {
                return false;
            }
        }
        public function isActive()
        {
            return $this->data['active'];
        }
        public function deactivate()
        {
            if (!$this->conn) {
                $this->conn = Database::getConnection();
            }
            $table = get_config('SessionTable');
            $sql = "UPDATE `$table` SET `active` = 0 WHERE `uid`=$this->uid";

            return $this->conn->query($sql) ? true : false;
        }
        public function getIP()
        {
            return $this->data['ip'];
        }
        public function getUserAgent() //can also do with IP address(getIP)
        {
            return $this->data['useragent'];
        }
        public function getFingerPrintId()
        {
            return $this->data['fingerPrintId'];
        }
        // TODO: To make this print the error in the login page
        public static function dispError($message, $status)
        {
            ?>
            <script>
                console.log("<?php echo $message; ?>");
            </script>
            <?php
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    $status = "danger";
    if ($error == "Login Expired, Login Again") {
        $status = "warning";
    }
    if ($error == "FingerPrint JS Doesn't Match") {
        $status = "danger";
    }
    if ($error == "User IP and Browser Doesn't Match") {
        $status = "danger";
    }
    if ($error == "IP or UserAgent or FingerPrint JS may be NULL") {
        $status = "danger";
    }
    if ($error == "User Not Found") {
        $status = "danger";
    }
    if ($error == "Password Doesn't Match") {
        $status = "danger";
    }
    usersession::dispError($error, $status);
}
