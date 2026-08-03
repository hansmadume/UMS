document.addEventListener("DOMContentLoaded", function () {
  const menuToggle = document.getElementById("menuToggle");
  const sidebar = document.querySelector(".sidebar");

  if (menuToggle && sidebar) {
    menuToggle.addEventListener("click", function () {
      sidebar.classList.toggle("open");
    });

    document.addEventListener("click", function (e) {
      if (window.innerWidth > 768) {
        return;
      }

      if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
        sidebar.classList.remove("open");
      }
    });
  }

  document.querySelectorAll(".mui-input").forEach(function (input) {
    if (input.value.trim() !== "") {
      input.classList.add("has-value");
    }

    input.addEventListener("input", function () {
      this.classList.toggle("has-value", this.value.trim() !== "");
    });
  });

  document.querySelectorAll(".mui-select").forEach(function (select) {
    if (select.value) {
      select.classList.add("has-value");
    }

    select.addEventListener("change", function () {
      this.classList.toggle("has-value", this.value !== "");
    });
  });

  document.querySelectorAll(".mui-btn").forEach(function (button) {
    button.addEventListener("click", function (e) {
      const oldRipple = this.querySelector(".ripple-effect");

      if (oldRipple) {
        oldRipple.remove();
      }

      const ripple = document.createElement("span");
      const rect = this.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);

      ripple.classList.add("ripple-effect");
      ripple.style.width = ripple.style.height = size + "px";
      ripple.style.left = e.clientX - rect.left - size / 2 + "px";
      ripple.style.top = e.clientY - rect.top - size / 2 + "px";

      this.appendChild(ripple);

      setTimeout(function () {
        ripple.remove();
      }, 600);
    });
  });

  document.querySelectorAll(".search-box .mui-input").forEach(function (input) {
    if (!input.hasAttribute("placeholder")) {
      input.setAttribute("placeholder", " ");
    }
  });

  document.querySelectorAll(".forgot-link").forEach(function (link) {
    link.addEventListener("click", function (event) {
      event.preventDefault();

      const messageId = link.getAttribute("aria-controls");
      const message = messageId ? document.getElementById(messageId) : null;

      if (message) {
        message.hidden = false;
        message.focus();
      }
    });
  });

  function showFormMessage(form, message, type) {
    let alert = form.querySelector(".form-validation-message");

    if (!alert) {
      alert = document.createElement("div");
      alert.className = "form-validation-message login-alert";
      form.prepend(alert);
    }

    alert.className =
      "form-validation-message login-alert " +
      (type === "success" ? "login-alert-info" : "login-alert-error");
    alert.textContent = message;
  }

  document.querySelectorAll(".ajax-search-form").forEach(function (form) {
    const targetSelector = form.getAttribute("data-target");
    const target = targetSelector ? document.querySelector(targetSelector) : null;
    let searchTimer = null;

    function runSearch() {
      if (!target) {
        form.submit();
        return;
      }

      const params = new URLSearchParams(new FormData(form));
      const url = form.getAttribute("action") + "?" + params.toString();

      target.style.opacity = "0.5";

      fetch(url, {
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      })
        .then(function (response) {
          return response.text();
        })
        .then(function (html) {
          const doc = new DOMParser().parseFromString(html, "text/html");
          const updatedTarget = doc.querySelector(targetSelector);

          if (updatedTarget) {
            target.innerHTML = updatedTarget.innerHTML;
            window.history.replaceState({}, "", url);
          }
        })
        .catch(function () {
          showFormMessage(form, "Search failed. Please try again.", "error");
        })
        .finally(function () {
          target.style.opacity = "1";
        });
    }

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      runSearch();
    });

    form.querySelectorAll("input[type='text'], input[type='search']").forEach(function (input) {
      input.addEventListener("input", function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(runSearch, 300);
      });
    });

    form.querySelectorAll("select").forEach(function (select) {
      select.addEventListener("change", runSearch);
    });
  });

  function validatePassword(password) {
    if (password.length < 8) {
      return "Password must be at least 8 characters.";
    }

    if (!/[A-Z]/.test(password)) {
      return "Password must include at least one uppercase letter.";
    }

    if (!/[a-z]/.test(password)) {
      return "Password must include at least one lowercase letter.";
    }

    if (!/[0-9]/.test(password)) {
      return "Password must include at least one number.";
    }

    return "";
  }

  document.querySelectorAll("form[data-validate]").forEach(function (form) {
    form.addEventListener("submit", function (event) {
      const validationType = form.getAttribute("data-validate");
      const name = form.querySelector("[name='name']");
      const username = form.querySelector("[name='username']");
      const email = form.querySelector("[name='email']");
      const password = form.querySelector("[name='password']");
      const confirmPassword = form.querySelector("[name='confirm_password']");
      const role = form.querySelector("[name='role'], [name='role_id']");
      const roleName = validationType === "role" ? form.querySelector("[name='name']") : null;
      const errors = [];

      if (validationType === "role") {
        if (!roleName || roleName.value.trim() === "") {
          errors.push("Role Name is required.");
        }
      } else {
        if (name && name.value.trim() === "") {
          errors.push("Full Name is required.");
        }

        if (validationType === "user") {
          if (username && username.value.trim() === "") {
            errors.push("Username is required.");
          } else if (username && username.value.trim().length < 4) {
            errors.push("Username must be at least 4 characters.");
          }

          if (email && email.value.trim() === "") {
            errors.push("Email is required.");
          } else if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
            errors.push("A valid email address is required.");
          }

          if (role && !role.disabled && role.value.trim() === "") {
            errors.push("Role is required.");
          }
        }

        if (password && password.value !== "") {
          const passwordError = validatePassword(password.value);

          if (passwordError !== "") {
            errors.push(passwordError);
          }

          if (confirmPassword && password.value !== confirmPassword.value) {
            errors.push("Password confirmation does not match.");
          }
        } else if (validationType === "user" && password && password.hasAttribute("required")) {
          errors.push("Password is required.");
        }
      }

      if (errors.length > 0) {
        event.preventDefault();
        showFormMessage(form, errors.join(" "), "error");
      }
    });
  });

  document.querySelectorAll(".status-badge").forEach(function (badge) {
    badge.style.cursor = "pointer";
    badge.addEventListener("click", function () {
      this.style.transform = "scale(0.95)";

      setTimeout(() => {
        this.style.transform = "scale(1)";
      }, 150);
    });
  });
});