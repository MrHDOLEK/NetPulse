export const sidebar = () => ({
  open: false,
  collapsed: localStorage.getItem("netpulse:rail") === "1",
  
  
  
  get expanded() {
    return !this.collapsed;
  },
  get railWidthClass() {
    return this.collapsed ? "w-[68px]" : "w-[230px]";
  },
  openDrawer() {
    this.open = true;
  },
  closeDrawer() {
    this.open = false;
  },
  toggleRail() {
    this.collapsed = !this.collapsed;
    localStorage.setItem("netpulse:rail", this.collapsed ? "1" : "0");
  },
});
