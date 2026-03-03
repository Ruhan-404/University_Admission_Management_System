    </main>
  </div>

  <script>
    // underline focus behaviour for .control inputs
    const controls = document.querySelectorAll(".control");
    controls.forEach(ctrl => {
      const input = ctrl.querySelector("input, select");
      if (!input) return;
      input.addEventListener("focus", () => ctrl.classList.add("focused"));
      input.addEventListener("blur", () => {
        if (input.value && input.tagName.toLowerCase() === "input") return;
        ctrl.classList.remove("focused");
      });
    });
  </script>
</body>
</html>
