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

  const themeToggle = document.getElementById("themeToggle");
  const themeToggleIcon = themeToggle ? themeToggle.querySelector(".theme-toggle-icon") : null;

  function setTheme(theme) {
    const normalizedTheme = theme === "light" ? "light" : "dark";
    const isLight = normalizedTheme === "light";

    document.documentElement.setAttribute("data-theme", normalizedTheme);

    if (themeToggle) {
      themeToggle.setAttribute("aria-pressed", String(isLight));
      themeToggle.setAttribute("aria-label", isLight ? "Switch to dark mode" : "Switch to light mode");
      themeToggle.setAttribute("title", isLight ? "Switch to dark mode" : "Switch to light mode");
    }

    if (themeToggleIcon) {
      themeToggleIcon.textContent = isLight ? "light_mode" : "dark_mode";
    }
  }

  if (themeToggle) {
    const activeTheme = document.documentElement.getAttribute("data-theme") || "dark";

    setTheme(activeTheme);

    themeToggle.addEventListener("click", function () {
      const currentTheme = document.documentElement.getAttribute("data-theme") === "light" ? "light" : "dark";
      const nextTheme = currentTheme === "light" ? "dark" : "light";

      setTheme(nextTheme);

      try {
        localStorage.setItem("ums-theme", nextTheme);
      } catch (error) {
        // Theme persistence is optional when storage is unavailable.
      }
    });
  }

  const notificationToggle = document.getElementById("notificationToggle");
  const notificationPanel = document.getElementById("notificationPanel");

  if (notificationToggle && notificationPanel) {
    const notificationKey = notificationToggle.getAttribute("data-notification-key") || "";
    const notificationStorageKey = "ums-read-notifications";

    function getNotificationBadge() {
      return notificationToggle.querySelector(".notification-badge");
    }

    function clearNotificationBadge() {
      const badge = getNotificationBadge();

      if (badge) {
        badge.remove();
      }
    }

    function markNotificationsRead() {
      if (notificationKey !== "") {
        try {
          localStorage.setItem(notificationStorageKey, notificationKey);
        } catch (error) {
          // Notification read state is optional when storage is unavailable.
        }
      }

      clearNotificationBadge();
    }

    try {
      if (notificationKey !== "" && localStorage.getItem(notificationStorageKey) === notificationKey) {
        clearNotificationBadge();
      }
    } catch (error) {
      // Ignore storage read errors.
    }

    notificationToggle.addEventListener("click", function (event) {
      event.stopPropagation();

      const isOpen = !notificationPanel.hidden;
      notificationPanel.hidden = isOpen;
      notificationToggle.setAttribute("aria-expanded", String(!isOpen));

      if (!isOpen) {
        markNotificationsRead();
      }
    });

    notificationPanel.addEventListener("click", function (event) {
      event.stopPropagation();
    });

    document.addEventListener("click", function () {
      if (!notificationPanel.hidden) {
        notificationPanel.hidden = true;
        notificationToggle.setAttribute("aria-expanded", "false");
      }
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && !notificationPanel.hidden) {
        notificationPanel.hidden = true;
        notificationToggle.setAttribute("aria-expanded", "false");
        notificationToggle.focus();
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

  let pendingLogoutForm = null;

  function ensureLogoutModal() {
    let modal = document.getElementById("logoutConfirmModal");

    if (modal) {
      return modal;
    }

    modal = document.createElement("div");
    modal.id = "logoutConfirmModal";
    modal.className = "confirm-modal";
    modal.hidden = true;
    modal.innerHTML =
      '<div class="confirm-modal-backdrop" data-confirm-cancel></div>' +
      '<div class="confirm-card" role="dialog" aria-modal="true" aria-labelledby="logoutConfirmTitle" aria-describedby="logoutConfirmMessage">' +
      '<div class="confirm-card-icon"><span class="material-icons">logout</span></div>' +
      '<div class="confirm-card-content">' +
      '<h3 id="logoutConfirmTitle">Confirm Logout</h3>' +
      '<p id="logoutConfirmMessage">Are you sure you want to log out?</p>' +
      '</div>' +
      '<div class="confirm-card-actions">' +
      '<button type="button" class="mui-btn mui-btn-outlined confirm-cancel" data-confirm-cancel>Stay Logged In</button>' +
      '<button type="button" class="mui-btn mui-btn-contained confirm-approve" data-confirm-approve>Log Out</button>' +
      '</div>' +
      '</div>';

    document.body.appendChild(modal);

    modal.querySelectorAll("[data-confirm-cancel]").forEach(function (button) {
      button.addEventListener("click", function () {
        closeLogoutModal();
      });
    });

    const approveButton = modal.querySelector("[data-confirm-approve]");

    if (approveButton) {
      approveButton.addEventListener("click", function () {
        const form = pendingLogoutForm;

        pendingLogoutForm = null;
        closeLogoutModal();

        if (form) {
          form.dataset.confirmed = "true";
          form.submit();
        }
      });
    }

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && !modal.hidden) {
        closeLogoutModal();
      }
    });

    return modal;
  }

  function openLogoutModal(form) {
    const modal = ensureLogoutModal();
    const message = form.getAttribute("data-confirm") || "Are you sure you want to log out?";
    const messageElement = modal.querySelector("#logoutConfirmMessage");
    const approveButton = modal.querySelector("[data-confirm-approve]");

    pendingLogoutForm = form;

    if (messageElement) {
      messageElement.textContent = message;
    }

    modal.hidden = false;
    document.body.classList.add("modal-open");

    if (approveButton) {
      approveButton.focus();
    }
  }

  function closeLogoutModal() {
    const modal = document.getElementById("logoutConfirmModal");

    pendingLogoutForm = null;

    if (modal) {
      modal.hidden = true;
    }

    document.body.classList.remove("modal-open");
  }

  document.querySelectorAll(".logout-form[data-confirm]").forEach(function (form) {
    form.addEventListener("submit", function (event) {
      if (form.dataset.confirmed === "true") {
        delete form.dataset.confirmed;
        return;
      }

      event.preventDefault();
      openLogoutModal(form);
    });
  });

  function formatUserTimeElements(timeZone) {
    const formatterOptions = {
      month: "2-digit",
      day: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      hour12: true,
    };

    if (timeZone) {
      formatterOptions.timeZone = timeZone;
    }

    const localTimeFormatter = new Intl.DateTimeFormat(undefined, formatterOptions);

    document.querySelectorAll("[data-local-time]").forEach(function (element) {
      const value = element.getAttribute("data-local-time");

      if (!value) {
        return;
      }

      const date = new Date(value);

      if (Number.isNaN(date.getTime())) {
        return;
      }

      element.textContent = localTimeFormatter.format(date);
      element.setAttribute("title", timeZone ? "Online time zone: " + timeZone : date.toString());
    });
  }

  function updateSidebarClock(date, timeZone, isOnline) {
    const timeElement = document.getElementById("sidebarClockTime");
    const dateElement = document.getElementById("sidebarClockDate");
    const clockElement = document.querySelector(".topbar-clock");

    if (!timeElement || !dateElement) {
      return;
    }

    const timeFormatterOptions = {
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
      hour12: true,
    };
    const dateFormatterOptions = {
      month: "2-digit",
      day: "2-digit",
      year: "numeric",
    };

    if (timeZone) {
      timeFormatterOptions.timeZone = timeZone;
      dateFormatterOptions.timeZone = timeZone;
    }

    timeElement.textContent = new Intl.DateTimeFormat(undefined, timeFormatterOptions).format(date);
    dateElement.textContent = new Intl.DateTimeFormat(undefined, dateFormatterOptions).format(date);

    if (clockElement) {
      clockElement.setAttribute("title", isOnline && timeZone ? "Online synced time · " + timeZone : "Current time");
    }
  }

  function startSidebarClock(baseDate, timeZone, isOnline) {
    const baseTimestamp = baseDate.getTime();
    const basePerformanceTime = performance.now();

    function tick() {
      const currentDate = new Date(baseTimestamp + (performance.now() - basePerformanceTime));

      updateSidebarClock(currentDate, timeZone, isOnline);
    }

    tick();
    window.setInterval(tick, 1000);
  }

  function applyOnlineUserTime() {
    formatUserTimeElements();

    fetch("https://worldtimeapi.org/api/ip", {
      cache: "no-store",
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error("Online time API request failed.");
        }

        return response.json();
      })
      .then(function (data) {
        const timeZone = data && typeof data.timezone === "string" ? data.timezone : "";
        const onlineDate = data && typeof data.datetime === "string" ? new Date(data.datetime) : new Date();

        if (timeZone !== "") {
          formatUserTimeElements(timeZone);
        }

        startSidebarClock(Number.isNaN(onlineDate.getTime()) ? new Date() : onlineDate, timeZone, timeZone !== "");
      })
      .catch(function () {
        formatUserTimeElements();
        startSidebarClock(new Date(), "", false);
      });
  }

  applyOnlineUserTime();

  function showFormMessage(form, message, type) {
    let alert = form.querySelector(".form-validation-message");

    if (!alert) {
      alert = document.createElement("div");
      alert.className = "form-validation-message login-alert";
      form.prepend(alert);
    }

    const messages = String(message)
      .split(".")
      .map(function (item) {
        return item.trim();
      })
      .filter(Boolean);

    alert.className =
      "form-validation-message login-alert " +
      (type === "success" ? "login-alert-info" : "login-alert-error");
    alert.setAttribute("role", "alert");
    alert.innerHTML =
      '<div class="form-validation-title">' +
      (type === "success" ? "Success" : "Please fix the following") +
      "</div>" +
      '<ul class="form-validation-list">' +
      messages
        .map(function (item) {
          return "<li>" + item + ".</li>";
        })
        .join("") +
      "</ul>";
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