</div> <!-- content padding -->

</main>
</div> <!-- layout -->
<!-- Bootstrap JS Bundle -->
<script>

function updateDateTime(){
const now = new Date();

const options={
weekday:'short',
year:'numeric',
month:'short',
day:'numeric',
hour:'2-digit',
minute:'2-digit',
second:'2-digit',
hour12:false
};

document.getElementById('currentDateTime').textContent =
now.toLocaleString('en-US',options);
}

updateDateTime();
setInterval(updateDateTime,1000);

</script>

<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> -->

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>

</body>
</html>