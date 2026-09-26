<?php
// Change '123456' to whatever you want your new password to be
$new_password = '123456'; 
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

echo "Your new password hash is: <br><br>";
echo "<strong>" . $hashed_password . "</strong>";
?>