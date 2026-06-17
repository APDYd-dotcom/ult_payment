<html>
<head>
<title>ULT Payment System</title>
  <style>

    form{
        display:flex;
        flex-direction:column;
    }

    form label{
        margin-top:10px;
    }

    form input{
        padding:10px;
        margin-top:5px;
        border:1px solid #ccc;
        border-radius:6px;
    }

    .buttons{
        margin-top:20px;
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:10px;
    }

    button{
        padding:10px;
        border:none;
        border-radius:6px;
        background:#2563eb;
        color:white;
        cursor:pointer;
    }

    button:hover{
        background:#1d4ed8;
    }
    .main{
        min-width: 70%;
    }
  </style>
</head>

<body>

<?php

?>

<div class="container">
    <main id="main-content" class="flex">
        <section class="main">
            <form>
                <div class="action">
                    <radio name="user" value="Admin">
                    <radio name="user" value="student">
                </div>
                <label for="email">Email</label>
                <input id="email" type="text" name="email">
                <label for="password">Password</label>
                <input id="password" type="password" name="password">

                <div class="buttons">
                    <button type="submit" name="login">Login</button>
                </div>
            </form>
        </section>
    </main>

</div>


</body>
</html>
