(() => {
  const dialog = document.getElementById("portal-login");
  const form = dialog.querySelector("form");
  const submit = form.querySelector('[type="submit"]');
  const options = dialog.querySelectorAll("[data-role]");
  const status = dialog.querySelector('[role="status"]');
  let role = "student";

  function select(option) {
    role = option.dataset.role;
    for (const button of options)
      button.setAttribute("aria-pressed", String(button === option));
    submit.textContent = "Enter " + option.textContent + " Portal";
    status.textContent = "";
  }

  for (const opener of document.querySelectorAll("[data-portal-login]")) {
    opener.addEventListener("click", (event) => {
      event.preventDefault();
      const nav = document.getElementById("navbar");
      if (nav?.classList.contains("active-menu"))
        document.getElementById("mobile-menu-btn")?.click();
      const option = Array.from(options).find(
        (item) => item.dataset.role === opener.dataset.portalLogin,
      );
      if (option && !submit.disabled) select(option);
      dialog.showModal();
      form.elements.username.focus();
    });
  }
  for (const option of options)
    option.addEventListener("click", () => select(option));
  dialog
    .querySelector(".portal-close")
    .addEventListener("click", () => dialog.close());
  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) dialog.close();
  });
  dialog.addEventListener("close", () => {
    form.elements.password.value = "";
    status.textContent = "";
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const requestedRole = role;
    submit.disabled = true;
    for (const option of options) option.disabled = true;
    status.textContent = "Signing in…";
    try {
      const response = await fetch("/api/portal/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          username: form.elements.username.value,
          password: form.elements.password.value,
          role: requestedRole,
        }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error);
      if (!dialog.open) return;
      sessionStorage.setItem(
        "nusha-portal-session",
        JSON.stringify(result.session),
      );
      window.location.assign("/portals?role=" + result.role);
    } catch (error) {
      if (dialog.open)
        status.textContent = error.message || "Sign-in failed. Try again.";
    } finally {
      submit.disabled = false;
      for (const option of options) option.disabled = false;
    }
  });
})();
