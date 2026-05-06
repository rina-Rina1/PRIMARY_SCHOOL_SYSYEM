<html>
<head>
<title>Welcome</title>

<style>
body{
    margin:0;
    font-family: Arial;
    background: linear-gradient(135deg, #2042d8, #ecff4346);
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
    color:white;
}

.box{
    text-align:center;
}

h1{
    font-size:40px;
    margin-bottom:10px;
}

p{
    font-size:18px;
}

/* simple pulse animation */
.logo{
    width: 150px;
    height: auto;
}

@keyframes pulse{
    0% {transform: scale(1);}
    50% {transform: scale(1.2);}
    100% {transform: scale(1);}
}
</style>

<!-- redirect after 3 seconds -->
<script>
setTimeout(function(){
    window.location.href = "login.php";
}, 3000);
</script>

</head>

<body>

<div style="text-align:center;">
    <img src="logo.png" alt="Logo"
         style="width:230px; height:230px; border-radius:50%; object-fit:cover;">
    <h1>Bright Horizon Primary School</h1>
</div>

</body>
</html>