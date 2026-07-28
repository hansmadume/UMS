/**
 * UMS - User Management System
 * JavaScript for interactive MUI effects
 */

document.addEventListener("DOMContentLoaded", function () {
  // =============================================
  // Mobile Sidebar Toggle
  // =============================================
  const menuToggle = document.getElementById("menuToggle");
  const sidebar = document.querySelector(".sidebar");

  if (menuToggle && sidebar) {
    menuToggle.addEventListener("click", function () {
      sidebar.classList.toggle("open");
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener("click", function (e) {
      if (window.innerWidth <= 768) {
        const isClickInsideSidebar = sidebar.contains(e.target);
        const isClickOnMenuToggle = menuToggle.contains(e.target);
        if (!isClickInsideSidebar && !isClickOnMenuToggle) {
          sidebar.classList.remove("open");
        }
      }
    });
  }

  // =============================================
  // MUI Input Float Label Fix
  // =============================================
  const inputs = document.querySelectorAll(".mui-input");
  inputs.forEach(function (input) {
    // If input has a value on load, add class
    if (input.value.trim() !== "") {
      input.classList.add("has-value");
    }

    input.addEventListener("input", function () {
      if (this.value.trim() !== "") {
        this.classList.add("has-value");
      } else {
        this.classList.remove("has-value");
      }
    });
  });

  // =============================================
  // Select Float Label Fix
  // =============================================
  const selects = document.querySelectorAll(".mui-select");
  selects.forEach(function (select) {
    // Mark as valid so label floats
    if (select.value) {
      select.classList.add("has-value");
    }

    select.addEventListener("change", function () {
      if (this.value) {
        this.classList.add("has-value");
      } else {
        this.classList.remove("has-value");
      }
    });
  });

  // =============================================
  // Button Ripple Effect
  // =============================================
  const buttons = document.querySelectorAll(".mui-btn");
  buttons.forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      // Remove any existing ripple
      const existingRipple = this.querySelector(".ripple-effect");
      if (existingRipple) {
        existingRipple.remove();
      }

      // Create ripple element
      const ripple = document.createElement("span");
      ripple.classList.add("ripple-effect");

      const rect = this.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top - size / 2;

      ripple.style.width = ripple.style.height = size + "px";
      ripple.style.left = x + "px";
      ripple.style.top = y + "px";

      this.appendChild(ripple);

      // Remove ripple after animation
      setTimeout(function () {
        ripple.remove();
      }, 600);
    });
  });

  // =============================================
  // Search Box Placeholder Management
  // =============================================
  const searchBoxes = document.querySelectorAll(".search-box .mui-input");
  searchBoxes.forEach(function (input) {
    // Ensure placeholder works with MUI label logic
    if (!input.hasAttribute("placeholder")) {
      input.setAttribute("placeholder", " ");
    }
  });

  // =============================================
  // Demo: Alert on status badge click (for demo)
  // =============================================
  const statusBadges = document.querySelectorAll(".status-badge");
  statusBadges.forEach(function (badge) {
    badge.style.cursor = "pointer";
    badge.addEventListener("click", function () {
      // Just a visual demo feedback
      this.style.transform = "scale(0.95)";
      setTimeout(() => {
        this.style.transform = "scale(1)";
      }, 150);
    });
  });

  console.log("UMS - UI initialized successfully");
});
