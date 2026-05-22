<!DOCTYPE html>
<html>
<head>
<style>
body  {background-color: powderblue;}
table {border-spacing: 1cm 0cm;}
h2    {color: blue;}
th    {text-align: left;}
p     {color: red; font-weight: bold;}
</style>
</head>

<body>

Hello. My web server still works.<br />
If this shows the current date and time, PHP works too:<br /><br />

<?php system("date"); ?>

<h2>Instance Info</h2>

<table>
<tr>
    <th>Configuration</th>
    <th>Value</th>
</tr>

<?php
$token = exec("curl -s -X PUT 'http://169.254.169.254/latest/api/token' -H 'X-aws-ec2-metadata-token-ttl-seconds: 21600'");
?>

<tr>
    <td><p>Private IP</p></td>
    <td>
        <?php
        system("curl -H 'X-aws-ec2-metadata-token: $token' http://169.254.169.254/latest/meta-data/local-ipv4");
        ?>
    </td>
</tr>

<tr>
    <td><p>Public IP</p></td>
    <td>
        <?php
        system("curl -H 'X-aws-ec2-metadata-token: $token' http://169.254.169.254/latest/meta-data/public-ipv4 2>&1 | grep -q '404 - Not Found'", $rc);

        if ($rc == 0) {
            echo "None found";
        } else {
            system("curl -H 'X-aws-ec2-metadata-token: $token' http://169.254.169.254/latest/meta-data/public-ipv4");
        }
        ?>
    </td>
</tr>

</table>

</body>
</html>